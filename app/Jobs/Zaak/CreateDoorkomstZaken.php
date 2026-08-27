<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Actions\Geospatial\CheckIntersects;
use App\EventForm\State\FormState;
use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Brick\Geo\Curve;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Brick\Geo\GeometryCollection;
use Brick\Geo\Point;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

/**
 * For route events (a zaaktype with `triggers_route_check = true`): creates
 * a "doorkomst" deelzaak for every municipality a route passes through
 * (excluding the start and end municipality) and copies the relevant
 * eigenschappen / initiator / documents / initial status.
 *
 * Input is a `Zaak`; the routes come from `form_state_snapshot` via
 * `ZaakeigenschappenMap::buildEventLocation()`. An event can have more than
 * one route drawn on the map, so all of them are read and the result is the
 * union of the municipalities they cross, minus the start and end
 * municipalities of all routes together.
 */
class CreateDoorkomstZaken implements ShouldQueue
{
    use Queueable;

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
        $lines = $this->extractLines($map->buildEventLocation($state));
        if ($lines === []) {
            return;
        }

        $engine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($engine);

        $passing = $this->passingMunicipalities($lines, $checkIntersects);

        $hoofdZaakMuniBrk = $this->zaak->municipality?->brk_identification;

        $passing = $passing->reject(fn ($m) => $hoofdZaakMuniBrk && $m->brk_identification === $hoofdZaakMuniBrk);
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
     * All routes drawn on the map, parsed with the very same parser the
     * submit flow uses. That parser understands both the current map state
     * (one FeatureCollection holding N routes) and the old repeater rows
     * kept in existing drafts.
     *
     * @param  array<string, mixed>  $eventLocation
     * @return list<Geometry>
     */
    private function extractLines(array $eventLocation): array
    {
        $line = $eventLocation['line'] ?? null;
        if (empty($line) || $line === 'None') {
            return [];
        }

        return array_values(array_filter(
            EventLocationGeometryBuilder::parseLines($line),
            static fn (Geometry $geometry) => ! $geometry->isEmpty(),
        ));
    }

    /**
     * The union of the municipalities the routes pass through, minus the
     * start and end municipalities of ALL routes. The exclusion is deliberately
     * global and not per route: a municipality where any route begins or ends
     * is a start or end municipality for this event, so it never gets a
     * doorkomst deelzaak, not even when another route merely passes through it.
     *
     * @param  list<Geometry>  $lines
     * @return Collection<int, Municipality>
     */
    private function passingMunicipalities(array $lines, CheckIntersects $checkIntersects): Collection
    {
        $excluded = [];
        $passing = new Collection;

        foreach ($lines as $line) {
            foreach ($this->boundaryPoints($line) as $point) {
                foreach ($checkIntersects->checkIntersectsWithModels($point) as $municipality) {
                    $excluded[] = $municipality->brk_identification;
                }
            }

            $passing = $passing->merge($checkIntersects->checkIntersectsWithModels($line));
        }

        return $passing
            ->reject(fn ($m) => in_array($m->brk_identification, $excluded, true))
            ->unique('brk_identification')
            ->values();
    }

    /**
     * Start and end points of a route. A route drawn as a MultiLineString has
     * no start point of its own, so its parts are walked instead.
     *
     * @return list<Point>
     */
    private function boundaryPoints(Geometry $geometry): array
    {
        if ($geometry instanceof Curve) {
            return $geometry->isEmpty() ? [] : [$geometry->startPoint(), $geometry->endPoint()];
        }

        if ($geometry instanceof GeometryCollection) {
            $points = [];
            foreach ($geometry->geometries() as $part) {
                foreach ($this->boundaryPoints($part) as $point) {
                    $points[] = $point;
                }
            }

            return $points;
        }

        return [];
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
                            'registratiedatum' => $newOzZaak->registratiedatum,
                            'status_name' => $newOzZaak->status_name ?? '',
                            'statustype_url' => $newOzZaak->statustype_url ?? '',
                            'organisator' => $organisator,
                            // Per-day times of a multi-day event do not exist
                            // as a zaakeigenschap, so carry them over from the
                            // parent case directly.
                            'dagen_evenement' => $this->zaak->reference_data->dagen_evenement,
                            'dagen_opbouw' => $this->zaak->reference_data->dagen_opbouw,
                            'dagen_afbouw' => $this->zaak->reference_data->dagen_afbouw,
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
