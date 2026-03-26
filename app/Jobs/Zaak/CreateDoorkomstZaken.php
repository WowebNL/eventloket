<?php

namespace App\Jobs\Zaak;

use App\Actions\Geospatial\CheckIntersects;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class CreateDoorkomstZaken implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $zaakUrlZGW) {}

    public function handle(Openzaak $openzaak, ObjectsApi $objectsapi): void
    {
        $ozZaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW.'?expand=zaakobjecten,eigenschappen,rollen,zaakinformatieobjecten,deelzaken')->toArray());
        $formSubmissionObject = new FormSubmissionObject(...$objectsapi->get(basename($ozZaak->data_object_url))->toArray());

        $zaaktype = Zaaktype::where(['zgw_zaaktype_url' => $ozZaak->zaaktype, 'is_active' => true])->first();

        if (! $zaaktype || ! $zaaktype->triggers_route_check) {
            return;
        }

        $lineArray = $formSubmissionObject->getLineGeoJsonArray();

        if (! $lineArray) {
            return;
        }

        /** @var LineString $line */
        $line = (new GeoJsonReader)->read(json_encode($lineArray));

        $geometryEngine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($geometryEngine);

        $allIntersecting = $checkIntersects->checkIntersectsWithModels($line);
        $startModels = $checkIntersects->checkIntersectsWithModels($line->startPoint());
        $endModels = $checkIntersects->checkIntersectsWithModels($line->endPoint());

        $excludedBrkIds = $startModels->pluck('brk_identification')
            ->merge($endModels->pluck('brk_identification'))
            ->unique()
            ->toArray();

        $passingMunicipalities = $allIntersecting->reject(
            fn ($item) => in_array($item->brk_identification, $excludedBrkIds)
        );

        if ($passingMunicipalities->isEmpty()) {
            return;
        }

        $organisation = Organisation::where('uuid', $formSubmissionObject->organisation_uuid)->first();
        $user = OrganiserUser::where('uuid', $formSubmissionObject->user_uuid)->first();

        foreach ($passingMunicipalities as $passingMunicipality) {
            /** @var Municipality $municipality */
            $municipality = Municipality::where('brk_identification', $passingMunicipality->brk_identification)
                ->with('doorkomstZaaktype')
                ->first();

            if (! $municipality || ! $municipality->doorkomst_zaaktype_id) {
                continue;
            }

            /** @var Zaaktype|null $doorkomstZaaktype */
            $doorkomstZaaktype = $municipality->doorkomstZaaktype;

            if (! $doorkomstZaaktype || ! $doorkomstZaaktype->is_active) {
                continue;
            }
            $alreadyExists = collect($ozZaak->deelzaken)
                ->contains(fn ($deelzaak) => ($deelzaak['zaaktype'] ?? null) === $doorkomstZaaktype->zgw_zaaktype_url);

            if ($alreadyExists) {
                continue;
            }

            $newZaakResponse = $openzaak->zaken()->zaken()->store([
                'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
                'bronorganisatie' => $ozZaak->bronorganisatie,
                'verantwoordelijkeOrganisatie' => $ozZaak->bronorganisatie,
                'startdatum' => $ozZaak->startdatum,
                'omschrijving' => $ozZaak->omschrijving,
                'zaakgeometrie' => $ozZaak->zaakgeometrie,
                'hoofdzaak' => $ozZaak->url,
            ]);

            $newZaakUrl = $newZaakResponse->toArray()['url'] ?? null;

            if (! $newZaakUrl) {
                Log::error('CreateDoorkomstZaken: failed to create deelzaak', [
                    'hoofdzaak' => $ozZaak->url,
                    'municipality' => $municipality->brk_identification,
                ]);

                continue;
            }

            $this->copyZaakeigenschappen($openzaak, $ozZaak, $newZaakUrl, $doorkomstZaaktype);
            $this->copyInitiator($openzaak, $ozZaak, $newZaakUrl, $doorkomstZaaktype);
            $this->copyDocumenten($openzaak, $ozZaak, $newZaakUrl);
            $this->createInitieleStatus($openzaak, $newZaakUrl, $doorkomstZaaktype);

            $newOzZaak = new OzZaak(...$openzaak->get($newZaakUrl.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen')->toArray());

            if ($ozZaak->initiator && $ozZaak->initiator->betrokkeneType === 'natuurlijk_persoon') {
                $organisator = $ozZaak->initiator->betrokkeneIdentificatie['voornamen'].' '.$ozZaak->initiator->betrokkeneIdentificatie['geslachtsnaam'];
            } elseif ($ozZaak->initiator && $ozZaak->initiator->betrokkeneType === 'niet_natuurlijk_persoon') {
                $organisator = $ozZaak->initiator->betrokkeneIdentificatie['statutaireNaam'].' - '.$ozZaak->initiator->contactpersoonRol['naam'];
            } else {
                $organisator = '';
            }

            Zaak::updateOrCreate(
                ['zgw_zaak_url' => $newZaakUrl],
                [
                    'public_id' => $newOzZaak->identificatie,
                    'zaaktype_id' => $doorkomstZaaktype->id,
                    'data_object_url' => $ozZaak->data_object_url,
                    'organisation_id' => $organisation?->id,
                    'organiser_user_id' => $user?->id,
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
                ]
            );
        }
    }

    private function copyZaakeigenschappen(Openzaak $openzaak, OzZaak $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        $newZaakUuid = basename($newZaakUrl);
        $catalogiEigenschappen = $openzaak->catalogi()->eigenschappen()->getAll(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url])
            ->map(fn ($eigenschap) => new CatalogiEigenschap(...$eigenschap));

        foreach ($ozZaak->eigenschappen_key_value as $naam => $waarde) {
            if (! $waarde) {
                continue;
            }

            $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $naam);

            if (! $catalogiEigenschap) {
                continue;
            }

            $openzaak->zaken()->zaken()->zaakeigenschappen($newZaakUuid)->store([
                'zaak' => $newZaakUrl,
                'eigenschap' => $catalogiEigenschap->url,
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
        $initiatorRoltype = $roltypen->first(fn ($roltype) => ($roltype['omschrijvingGeneriek'] ?? null) === 'initiator');

        if (! $initiatorRoltype) {
            Log::warning('CreateDoorkomstZaken: no initiator roltype found for zaaktype', [
                'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
                'zaak' => $newZaakUrl,
            ]);

            return;
        }

        $openzaak->zaken()->rollen()->store([
            'zaak' => $newZaakUrl,
            'betrokkeneType' => $ozZaak->initiator->betrokkeneType,
            'roltype' => $initiatorRoltype['url'],
            'roltoelichting' => $ozZaak->initiator->omschrijving,
            'betrokkeneIdentificatie' => $ozZaak->initiator->betrokkeneIdentificatie,
            'contactpersoonRol' => $ozZaak->initiator->contactpersoonRol ?: null,
        ]);
    }

    private function copyDocumenten(Openzaak $openzaak, OzZaak $ozZaak, string $newZaakUrl): void
    {
        $zaakinformatieobjecten = $openzaak->zaken()->zaakinformatieobjecten()->getAll(['zaak' => $ozZaak->url]);

        foreach ($zaakinformatieobjecten as $zio) {
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

        $initiaalStatustype = $statustypen
            ->sortBy('volgnummer')
            ->first();

        if (! $initiaalStatustype) {
            Log::warning('CreateDoorkomstZaken: no statustype found for zaaktype', [
                'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
                'zaak' => $newZaakUrl,
            ]);

            return;
        }

        $openzaak->zaken()->statussen()->store([
            'zaak' => $newZaakUrl,
            'statustype' => $initiaalStatustype['url'],
            'datumStatusGezet' => now()->toIso8601String(),
        ]);
    }
}
