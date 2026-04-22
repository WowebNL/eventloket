<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 6: Vergunningsplichtig scan.
 *
 * Deze stap is een vragenboom die bepaalt of het evenement vergunningsplichtig
 * is of dat een lichte melding volstaat. De organisator beantwoordt vijf
 * Ja/Nee-vragen over geluid, openingstijden, locatie, aantal bezoekers en
 * impact op het verkeer. Elk Ja-antwoord onthult de volgende vraag; bij een
 * Nee klapt de boom dicht en valt het evenement in het vergunningsregime.
 */
final class VergunningsplichtigScanScenarios implements ScenarioProvider
{
    public const STAP_VERGUNNINGSPLICHTIG_SCAN = 'd87c01ce-8387-43b0-a8c8-e6cf5abb6da1';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Vragenboom om vergunning-plicht vs melding te bepalen';
    }

    public static function inleiding(): string
    {
        return 'Op basis van vijf voortschrijdende Ja/Nee-vragen stelt het systeem vast '
            .'of het evenement lichtvoetig gemeld kan worden of dat er een volledige '
            .'vergunningaanvraag nodig is. Zodra één vraag met "Nee" beantwoord wordt, '
            .'ligt de route vast op vergunning en stopt de vragenboom — de organisator '
            .'krijgt dan geen verdere meldingsvragen te zien.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Eerste meldingsvraag verschijnt bij klein evenement met kleine objecten' => [[
                'naam' => 'Eerste meldingsvraag komt vrij als objecten klein én gemeente heeft report_question_1',
                'omschrijving' =>
                    'Als de organisator aangeeft dat geplaatste objecten kleiner zijn dan de '
                    .'gemeente-grens, én de gemeente heeft de eerste aanvullende vraag '
                    .'geconfigureerd (`gemeenteVariabelen.report_question_1`), verschijnt '
                    .'meldingvraag1 als vervolgvraag in het formulier.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VERGUNNINGSPLICHTIG_SCAN,
                'trigger_velden' => [
                    'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
                    'gemeenteVariabelen.report_question_1',
                ],
                'gegeven' => [
                    'indienErObjectenGeplaatstWordenZijnDezeDanKleiner' => 'Ja',
                    'gemeenteVariabelen' => [
                        'report_question_1' => 'Zijn de activiteiten tussen 08:00 en 22:00?',
                    ],
                ],
                'verwacht' => [
                    'field_hidden.meldingvraag1' => false,
                ],
            ]],

            'Groot evenement — direct naar vergunningsplichtig' => [[
                'naam' => 'Bij "aantal aanwezigen niet kleiner dan drempel" blijft vergunningsplicht',
                'omschrijving' =>
                    'Zodra de organisator al bij de eerste vraag aangeeft dat het aantal aanwezigen '
                    .'NIET onder de gemeentelijke drempel ligt, stopt de melding-route direct. '
                    .'Het systeem markeert de aanvraag als vergunningsaanvraag en verbergt de '
                    .'content-block die naar melding zou leiden — de organisator wordt '
                    .'doorgestuurd naar de volledige vergunningsprocedure.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VERGUNNINGSPLICHTIG_SCAN,
                'trigger_velden' => ['isHetAantalAanwezigenBijUwEvenementMinderDanSdf'],
                'gegeven' => [
                    'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Nee',
                ],
                'verwacht' => [
                    'isVergunningaanvraag' => true,
                    'field_hidden.MeldingTekst' => true,
                ],
            ]],
        ];
    }
}
