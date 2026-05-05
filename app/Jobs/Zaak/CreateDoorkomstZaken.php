<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Actions\Geospatial\CheckIntersects;
use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Normalizers\OpenFormsNormalizer;
use App\Support\Helpers\ArrayHelper;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

/**
 * Voor route-events (zaaktype met `triggers_route_check = true`): maakt
 * per gemeente waar de route doorheen loopt (exclusief start- en
 * eindgemeente) een "doorkomst"-deelzaak aan en kopieert relevante
 * eigenschappen / initiator / documenten / initiële status.
 *
 * Input is nu `Zaak`; de LineString komt uit `form_state_snapshot`
 * via `ZaakeigenschappenMap::buildEventLocation()`.
 */
class CreateDoorkomstZaken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(Openzaak $openzaak, ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url || ! $this->zaak->zaaktype) {
            return;
        }
        if (! $this->zaak->zaaktype->triggers_route_check) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $lineArray = $this->extractLineArray($map->buildEventLocation($state));
        if (! $lineArray) {
            return;
        }

        /** @var LineString $line */
        $line = (new GeoJsonReader)->read((string) json_encode($lineArray));

        $engine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($engine);

        $all = $checkIntersects->checkIntersectsWithModels($line);
        $startModels = $checkIntersects->checkIntersectsWithModels($line->startPoint());
        $endModels = $checkIntersects->checkIntersectsWithModels($line->endPoint());

        $excluded = $startModels->pluck('brk_identification')
            ->merge($endModels->pluck('brk_identification'))
            ->unique()
            ->toArray();

        $passing = $all->reject(fn ($m) => in_array($m->brk_identification, $excluded, true));
        if ($passing->isEmpty()) {
            return;
        }

        $ozZaak = new OzZaak(...$openzaak->get(
            $this->zaak->zgw_zaak_url.'?expand=zaakobjecten,eigenschappen,rollen,zaakinformatieobjecten,deelzaken'
        )->toArray());

        foreach ($passing as $muniRef) {
            $this->createDeelzaakFor($openzaak, $ozZaak, $muniRef, $state);
        }
    }

    /**
     * @param  array<string, mixed>  $eventLocation
     * @return array<string, mixed>|null
     */
    private function extractLineArray(array $eventLocation): ?array
    {
        $line = $eventLocation['line'] ?? null;
        if (! $line || $line === 'None') {
            return null;
        }

        $json = is_array($line) ? json_encode($line) : OpenFormsNormalizer::normalizeGeoJson($line);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return ArrayHelper::findElementWithKey($decoded, 'coordinates');
    }

    private function createDeelzaakFor(Openzaak $openzaak, OzZaak $hoofdZaak, Municipality $muniRef, FormState $state): void
    {
        /** @var Municipality|null $municipality */
        $municipality = Municipality::where('brk_identification', $muniRef->brk_identification)
            ->with('doorkomstZaaktype')
            ->first();

        if (! $municipality || ! $municipality->doorkomst_zaaktype_id) {
            return;
        }

        /** @var Zaaktype|null $doorkomstZaaktype */
        $doorkomstZaaktype = $municipality->doorkomstZaaktype;
        if (! $doorkomstZaaktype || ! $doorkomstZaaktype->is_active) {
            return;
        }

        $alreadyExists = collect($hoofdZaak->deelzaken)
            ->contains(fn ($deel) => ($deel['zaaktype'] ?? null) === $doorkomstZaaktype->zgw_zaaktype_url);
        if ($alreadyExists) {
            return;
        }

        $response = $openzaak->zaken()->zaken()->store([
            'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
            'bronorganisatie' => $hoofdZaak->bronorganisatie,
            'verantwoordelijkeOrganisatie' => $hoofdZaak->bronorganisatie,
            'startdatum' => $hoofdZaak->startdatum,
            'omschrijving' => $hoofdZaak->omschrijving,
            'zaakgeometrie' => $hoofdZaak->zaakgeometrie,
            'hoofdzaak' => $hoofdZaak->url,
        ]);

        $newZaakUrl = $response->toArray()['url'] ?? null;
        if (! $newZaakUrl) {
            Log::error('CreateDoorkomstZaken: failed to create deelzaak', [
                'hoofdzaak' => $hoofdZaak->url,
                'municipality' => $municipality->brk_identification,
            ]);

            return;
        }

        $this->copyZaakeigenschappen($openzaak, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype);
        $this->copyInitiator($openzaak, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype);
        $this->copyDocumenten($openzaak, $hoofdZaak, $newZaakUrl);
        $this->createInitieleStatus($openzaak, $newZaakUrl, $doorkomstZaaktype);

        $newOzZaak = new OzZaak(...$openzaak->get(
            $newZaakUrl.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen'
        )->toArray());

        $organisator = $this->resolveOrganisatorLabel($hoofdZaak);

        Zaak::updateOrCreate(
            ['zgw_zaak_url' => $newZaakUrl],
            [
                'public_id' => $newOzZaak->identificatie,
                'zaaktype_id' => $doorkomstZaaktype->id,
                'data_object_url' => null, // Objects API is in nieuwe flow weg
                'organisation_id' => $this->zaak->organisation_id,
                'organiser_user_id' => $this->zaak->organiser_user_id,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        $newOzZaak->eigenschappen_key_value,
                        [
                            'registratiedatum' => $newOzZaak->registratiedatum ?? '',
                            'status_name' => $newOzZaak->status_name ?? '',
                            'statustype_url' => $newOzZaak->statustype_url ?? '',
                            'organisator' => $organisator,
                        ]
                    )
                ),
                // Deelzaken krijgen dezelfde snapshot mee zodat ze zelfstandig
                // vervolg-acties kunnen doen zonder van de hoofdzaak af te
                // hangen.
                'form_state_snapshot' => $state->toSnapshot(),
            ]
        );
    }

    private function resolveOrganisatorLabel(OzZaak $ozZaak): string
    {
        if (! $ozZaak->initiator) {
            return '';
        }

        if ($ozZaak->initiator->betrokkeneType === 'natuurlijk_persoon') {
            $id = $ozZaak->initiator->betrokkeneIdentificatie;

            return trim(($id['voornamen'] ?? '').' '.($id['geslachtsnaam'] ?? ''));
        }

        if ($ozZaak->initiator->betrokkeneType === 'niet_natuurlijk_persoon') {
            $id = $ozZaak->initiator->betrokkeneIdentificatie;
            $contactNaam = $ozZaak->initiator->contactpersoonRol['naam'] ?? '';

            return trim(($id['statutaireNaam'] ?? '').' - '.$contactNaam);
        }

        return '';
    }

    private function copyZaakeigenschappen(Openzaak $openzaak, OzZaak $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        $newUuid = basename($newZaakUrl);
        $catalogi = $openzaak->catalogi()->eigenschappen()->getAll(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url])
            ->map(fn ($e) => new CatalogiEigenschap(...$e));

        foreach ($ozZaak->eigenschappen_key_value as $naam => $waarde) {
            if (! $waarde) {
                continue;
            }
            $cat = $catalogi->firstWhere('naam', $naam);
            if (! $cat) {
                continue;
            }
            $openzaak->zaken()->zaken()->zaakeigenschappen($newUuid)->store([
                'zaak' => $newZaakUrl,
                'eigenschap' => $cat->url,
                'waarde' => $waarde,
            ]);
        }
    }

    private function copyInitiator(Openzaak $openzaak, OzZaak $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        if (! $ozZaak->initiator) {
            return;
        }

        $roltypen = $openzaak->catalogi()->roltypen()->getAll(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url]);
        $initiator = $roltypen->first(fn ($r) => ($r['omschrijvingGeneriek'] ?? null) === 'initiator');
        if (! $initiator) {
            Log::warning('CreateDoorkomstZaken: no initiator roltype', ['zaak' => $newZaakUrl]);

            return;
        }

        $openzaak->zaken()->rollen()->store([
            'zaak' => $newZaakUrl,
            'betrokkeneType' => $ozZaak->initiator->betrokkeneType,
            'roltype' => $initiator['url'],
            'roltoelichting' => $ozZaak->initiator->omschrijving,
            'betrokkeneIdentificatie' => $ozZaak->initiator->betrokkeneIdentificatie,
            'contactpersoonRol' => $ozZaak->initiator->contactpersoonRol ?: null,
        ]);
    }

    private function copyDocumenten(Openzaak $openzaak, OzZaak $ozZaak, string $newZaakUrl): void
    {
        $zios = $openzaak->zaken()->zaakinformatieobjecten()->getAll(['zaak' => $ozZaak->url]);
        foreach ($zios as $zio) {
            $informatieobjectUrl = Arr::get($zio, 'informatieobject');
            if (! $informatieobjectUrl) {
                continue;
            }
            $openzaak->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $newZaakUrl,
                'informatieobject' => $informatieobjectUrl,
            ]);
        }
    }

    private function createInitieleStatus(Openzaak $openzaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        $statustypen = $openzaak->catalogi()->statustypen()->getAll(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url]);
        $initieel = $statustypen->sortBy('volgnummer')->first();
        if (! $initieel) {
            Log::warning('CreateDoorkomstZaken: no statustype', ['zaak' => $newZaakUrl]);

            return;
        }

        $openzaak->zaken()->statussen()->store([
            'zaak' => $newZaakUrl,
            'statustype' => $initieel['url'],
            'datumStatusGezet' => now()->toIso8601String(),
        ]);
    }
}
