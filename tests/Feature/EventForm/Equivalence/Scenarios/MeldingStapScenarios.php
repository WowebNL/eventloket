<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 7: Melding.
 *
 * De Melding-stap is alleen van toepassing als uit de vragenboom op stap 6
 * bleek dat het evenement met een lichte melding volstaat. Zodra elders in
 * het formulier blijkt dat het toch een vergunningsaanvraag of een
 * vooraankondiging is, wordt deze stap niet-van-toepassing gemaakt en in
 * de wizard-sidebar doorgestreept.
 */
final class MeldingStapScenarios implements ScenarioProvider
{
    public const STAP_MELDING = '5f986f16-6a3a-4066-9383-d71f09877f47';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Melding-stap verdwijnt bij vooraankondiging of vergunningsroute';
    }

    public static function inleiding(): string
    {
        return 'De Melding-stap in de sidebar moet wegvallen zodra duidelijk wordt dat '
            .'het geen melding-procedure is: óf de organisator heeft gekozen voor '
            .'vooraankondiging, óf de vragenboom concludeert dat het een volledige '
            .'vergunningsaanvraag wordt.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Melding-stap niet van toepassing bij vooraankondiging' => [[
                'naam' => 'Vooraankondiging → Melding-stap wordt doorgestreept',
                'omschrijving' =>
                    'Zodra de organisator bij "waarvoor wilt u Eventloket gebruiken?" kiest voor '
                    .'"vooraankondiging", is de Melding-stap niet relevant. Het systeem markeert '
                    .'de stap als niet-van-toepassing; in de sidebar verschijnt hij doorgestreept.',
                'categorie' => 'visibility',
                'stap' => self::STAP_MELDING,
                'trigger_velden' => ['waarvoorWiltUEventloketGebruiken'],
                'gegeven' => [
                    'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
                ],
                'verwacht' => [
                    'step_applicable.'.self::STAP_MELDING => false,
                ],
            ]],

            'Melding-stap niet van toepassing bij vergunningsroute' => [[
                'naam' => 'Groot evenement (> drempel aanwezigen) → Melding-stap wordt doorgestreept',
                'omschrijving' =>
                    'Als de organisator al op stap 6 aangeeft dat het aantal aanwezigen boven de '
                    .'drempel ligt, start de vergunningsroute. De Melding-stap is dan niet van '
                    .'toepassing en wordt in de sidebar doorgestreept.',
                'categorie' => 'visibility',
                'stap' => self::STAP_MELDING,
                'trigger_velden' => ['isHetAantalAanwezigenBijUwEvenementMinderDanSdf'],
                'gegeven' => [
                    'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Nee',
                ],
                'verwacht' => [
                    'step_applicable.'.self::STAP_MELDING => false,
                ],
            ]],
        ];
    }
}
