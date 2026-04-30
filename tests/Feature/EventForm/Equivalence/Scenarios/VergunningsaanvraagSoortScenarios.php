<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 9: Vergunningsaanvraag: soort.
 */
final class VergunningsaanvraagSoortScenarios implements ScenarioProvider
{
    public const STAP_SOORT = 'ae44ab5b-c068-4ceb-b121-6e6907f78ef9';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Vergunningsaanvraag-details alleen bij de vergunningsroute';
    }

    public static function inleiding(): string
    {
        return 'Deze pagina vraagt naar soort-specifieke details over de '
            .'vergunningaanvraag. Als de aanvraag een vooraankondiging of een '
            .'melding is (geen gebiedsafsluiting), is deze pagina niet nodig en '
            .'wordt 8n doorgestreept in de sidebar.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Soort-stap niet van toepassing bij vooraankondiging' => [[
                'naam' => 'Vooraankondiging → "Vergunningsaanvraag: soort" wordt doorgestreept',
                'omschrijving' => 'Een vooraankondiging vraagt minder details dan een volledige vergunning. '
                    .'Zodra de organisator "vooraankondiging" kiest, hoeft de soort-stap niet '
                    .'ingevuld te worden en wordt 8n als niet-van-toepassing gemarkeerd.',
                'categorie' => 'visibility',
                'stap' => self::STAP_SOORT,
                'trigger_velden' => ['waarvoorWiltUEventloketGebruiken'],
                'gegeven' => [
                    'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
                ],
                'verwacht' => [
                    'step_applicable.'.self::STAP_SOORT => false,
                ],
            ]],

            'Soort-stap niet van toepassing bij melding-route' => [[
                'naam' => 'Geen wegafsluiting (Nee) → "Vergunningsaanvraag: soort" wordt doorgestreept',
                'omschrijving' => 'Als het evenement geen wegen of gebiedsontsluiting afsluit, valt het in '
                    .'het melding-regime. De soort-stap is dan niet relevant en wordt in de '
                    .'sidebar doorgestreept.',
                'categorie' => 'visibility',
                'stap' => self::STAP_SOORT,
                'trigger_velden' => ['wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer'],
                'gegeven' => [
                    'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
                ],
                'verwacht' => [
                    'step_applicable.'.self::STAP_SOORT => false,
                ],
            ]],
        ];
    }
}
