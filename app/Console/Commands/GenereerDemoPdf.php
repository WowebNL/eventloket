<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Dev-command dat een voorbeeld-inzendingsbewijs PDF genereert zonder
 * DB-afhankelijkheid — handig om het visuele resultaat te bekijken.
 * De PDF wordt naar `storage/app/demo-submission-report.pdf` geschreven.
 */
class GenereerDemoPdf extends Command
{
    protected $signature = 'eventform:genereer-demo-pdf';

    protected $description = 'Genereer een demo PDF-inzendingsbewijs (in-memory, geen DB nodig)';

    public function handle(): int
    {
        // Volledig gevulde demo-state: een fictief festival met polygon-
        // locatie, route, adres-Repeater, Risicoscan, alle aanvraag-
        // antwoorden. Zo zie je hoe de PDF eruit ziet als een
        // organisator alles invult.
        $state = new FormState(values: [
            // Stap 1: Contactgegevens
            'watIsUwVoornaam' => 'Eva',
            'watIsUwAchternaam' => 'de Vries',
            'watIsUwEMailadres' => 'eva@stadsfestival.nl',
            'watIsUwTelefoonnummer' => '06-12345678',
            'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
            'watIsDeNaamVanUwOrganisatie' => 'Stichting Stadsfestival Heerlen',
            'emailadresOrganisatie' => 'info@stadsfestival.nl',
            'telefoonnummerOrganisatie' => '045-1234567',
            'postcode1' => '6411CD',
            'huisnummer1' => '1',
            'straatnaam1' => 'Markt',
            'plaatsnaam1' => 'Heerlen',

            // Stap 2: Het evenement
            'watIsDeNaamVanHetEvenementVergunning' => 'Stadsfestival Heerlen 2026',
            'soortEvenement' => 'Festival',
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Tweedaags muziekfestival in het centrum van Heerlen, met 4 podia, foodtrucks en een avondparade. Verwacht ~5000 bezoekers per dag.',

            // Stap 3: Locatie (polygon op kaart + adres in Repeater + lijn voor route)
            'waarVindtHetEvenementPlaats' => ['gebouw', 'buiten', 'route'],
            'adresVanDeGebouwEn' => [
                [
                    'naamVanDeLocatieGebouw' => 'Theater Heerlen',
                    'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                        'postcode' => '6411CD',
                        'huisnummer' => '1',
                        'straatnaam' => 'Markt',
                        'plaatsnaam' => 'Heerlen',
                    ],
                ],
            ],
            'locatieSOpKaart' => [
                [
                    'naamVanDeLocatieKaart' => 'Centrumplein',
                    'buitenLocatieVanHetEvenement' => [
                        'lat' => 50.8867,
                        'lng' => 5.9810,
                        'geojson' => [
                            'type' => 'FeatureCollection',
                            'features' => [
                                [
                                    'type' => 'Feature',
                                    'geometry' => [
                                        'type' => 'Polygon',
                                        'coordinates' => [[
                                            [5.9800, 50.8860],
                                            [5.9820, 50.8860],
                                            [5.9820, 50.8875],
                                            [5.9800, 50.8875],
                                            [5.9800, 50.8860],
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'routesOpKaart' => [
                [
                    'naamVanDeRoute' => 'Avondparade-route',
                    'routeVanHetEvenement' => [
                        'lat' => 50.8867,
                        'lng' => 5.9810,
                        'geojson' => [
                            'type' => 'FeatureCollection',
                            'features' => [
                                [
                                    'type' => 'Feature',
                                    'geometry' => [
                                        'type' => 'LineString',
                                        'coordinates' => [
                                            [5.9790, 50.8855],
                                            [5.9805, 50.8865],
                                            [5.9820, 50.8870],
                                            [5.9835, 50.8880],
                                            [5.9850, 50.8885],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'watVoorEvenementGaatPlaatsvindenOpDeRoute1' => 'optocht',
                ],
            ],

            // Stap 4: Tijden
            'EvenementStart' => '2026-08-15T16:00',
            'EvenementEind' => '2026-08-16T23:00',
            'OpbouwStart' => '2026-08-14T08:00',
            'OpbouwEind' => '2026-08-15T15:30',
            'AfbouwStart' => '2026-08-16T23:00',
            'AfbouwEind' => '2026-08-17T18:00',

            // Stap 5: aanvraag, niet alleen vooraankondiging
            'waarvoorWiltUEventloketGebruiken' => 'evenement',

            // Stap 6: Vergunningsplichtig scan — wegen=Ja → vergunning
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',

            // Stap 8: Risicoscan
            'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',
            'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.75__2',
            'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0',
            'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0',

            // Stap 9: Vergunningsaanvraag soort
            'voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement' => 'Nee',
            'watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld' => '10000',
            'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX' => '5000',
            'watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement' => '45JaarEnOuder',
            'isUwEvenementXGratisToegankelijkVoorHetPubliek' => 'Nee',
            'isUwEvenementToegankelijkVoorMensenMetEenBeperking' => 'Ja',

            // Stap 10: Vergunningaanvraag kenmerken (CheckboxList)
            'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A1', 'A3', 'A5', 'A8'],

            // Stap 11: Voorzieningen
            'welkeVoorzieningenZijnAanwezigBijUwEvenement' => ['A12', 'A15', 'A18', 'A20'],

            // Stap 13: Maatregelen
            'wilUGebruikMakenVanGemeentelijkeHulpmiddelen' => 'Ja',

            // Stap 15: Overig
            'wiltUPromotieMakenVoorUwEvenement' => 'Ja',
            'geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX' => 'Ja',
            'organiseertUUwEvenementXVoorDeEersteKeer' => 'Nee',
            'hanteertUHuisregelsVoorUwEvenementX' => 'Ja',
            'heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement' => 'Ja',

            // System / afgeleid
            'risicoClassificatie' => 'B',
        ]);

        $refData = new ZaakReferenceData(
            start_evenement: '2026-06-14T14:00:00+02:00',
            eind_evenement: '2026-06-14T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ingediend',
            statustype_url: '',
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Buurtcentrum De Hoek',
            naam_evenement: 'Buurtfeest Testlaan',
            organisator: 'Media Tuin',
            aanwezigen: '80',
            types_evenement: 'Buurtfeest',
            start_opbouw: '2026-06-14T12:00:00+02:00',
            eind_opbouw: '2026-06-14T13:30:00+02:00',
            start_afbouw: '2026-06-14T18:00:00+02:00',
            eind_afbouw: '2026-06-14T19:30:00+02:00',
        );

        $zaak = new Zaak([
            'public_id' => 'DEMO-PDF-'.substr(uniqid(), -6),
            'zgw_zaak_url' => 'https://example.com/demo/'.uniqid(),
        ]);
        $zaak->id = (string) Str::uuid();
        $zaak->reference_data = $refData;
        $zaak->form_state_snapshot = $state->toSnapshot();
        $zaak->created_at = now();
        $zaak->setRelation('zaaktype', new Zaaktype(['name' => 'Evenementenvergunning gemeente Maastricht']));
        $zaak->setRelation('organisation', new Organisation(['name' => 'Media Tuin']));

        $rows = [
            'Naam evenement' => $refData->naam_evenement,
            'Type evenement' => $refData->types_evenement,
            'Omschrijving' => $state->get('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning'),
            'Locatie' => $refData->naam_locatie_evenement,
            'Start evenement' => $this->human($refData->start_evenement),
            'Eind evenement' => $this->human($refData->eind_evenement),
            'Start opbouw' => $this->human($refData->start_opbouw),
            'Eind opbouw' => $this->human($refData->eind_opbouw),
            'Start afbouw' => $this->human($refData->start_afbouw),
            'Eind afbouw' => $this->human($refData->eind_afbouw),
            'Verwacht aantal aanwezigen' => $refData->aanwezigen,
            'Risicoclassificatie' => $refData->risico_classificatie,
            'Organisator' => $refData->organisator,
            'Naam contactpersoon' => trim(((string) $state->get('watIsUwVoornaam')).' '.((string) $state->get('watIsUwAchternaam'))),
            'E-mailadres' => $state->get('watIsUwEMailadres'),
            'Telefoonnummer' => $state->get('watIsUwTelefoonnummer'),
            'KvK-nummer' => $state->get('watIsHetKamerVanKoophandelNummerVanUwOrganisatie'),
            'Organisatienaam' => $state->get('watIsDeNaamVanUwOrganisatie'),
        ];

        // De Blade-template verwacht `sections` (sinds de E-refactor):
        // SubmissionReport bouwt die uit alle stappen + ingevulde velden.
        $sections = app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport());

        $evenementInGemeente = $state->get('evenementInGemeente');
        $gemeenteNaam = is_array($evenementInGemeente) ? ($evenementInGemeente['name'] ?? null) : null;

        $pdf = Pdf::loadView('pdf.submission-report', [
            'zaak' => $zaak,
            'state' => $state,
            'sections' => $sections,
            'gemeenteNaam' => $gemeenteNaam,
            'risicoClassificatie' => $state->get('risicoClassificatie'),
            // `rows` voor backward-compat — niet door de nieuwe template
            // gebruikt maar handig voor lokaal debug-vergelijken.
            'rows' => $rows,
        ])->setPaper('a4');

        $target = storage_path('app/demo-submission-report.pdf');
        file_put_contents($target, $pdf->output());

        $this->info('PDF geschreven naar: '.$target);
        $this->info('Host-pad: storage/app/demo-submission-report.pdf');

        return self::SUCCESS;
    }

    private function human(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }

        try {
            return Carbon::parse($iso, 'Europe/Amsterdam')->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
