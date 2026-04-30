<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 14: Vergunningsaanvraag: extra activiteiten.
 *
 * Op deze pagina vinkt de organisator bijzondere activiteiten aan (ballon-
 * optochten, lasershows, vuurwerk, etc.). Elke aangevinkte activiteit opent
 * een inhoud-blok met relevante regelgeving of uitleg én activeert deze
 * pagina in de wizard-sidebar.
 */
final class ExtraActiviteitenScenarios implements ScenarioProvider
{
    public const STAP_EXTRA_ACTIVITEITEN = '6e285ace-f891-4324-b54e-639c1cfff9fa';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Inhoud-blokken per bijzondere activiteit';
    }

    public static function inleiding(): string
    {
        return 'Voor bijzondere activiteiten als ballonnen oplaten of een lasershow '
            .'toont het formulier specifieke inhoud-blokken met regelgeving. Elke '
            .'aanvinken activeert ook de pagina "extra activiteiten" in de sidebar.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Ballonnen-activiteit toont regelgeving-blok' => [[
                'naam' => 'Ballonnen oplaten (A37) → contentBalon-blok + stap actief',
                'omschrijving' => 'Als de organisator aangeeft ballonnen op te laten (activiteit A37), toont '
                    .'het formulier een inhoud-blok met de regelgeving rond ballon-oplatingen. De '
                    .'pagina "extra activiteiten" wordt daarmee ook van toepassing.',
                'categorie' => 'visibility',
                'stap' => self::STAP_EXTRA_ACTIVITEITEN,
                'trigger_velden' => ['welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX'],
                'gegeven' => [
                    'welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX' => ['A37' => true],
                ],
                'verwacht' => [
                    'field_hidden.contentBalon' => false,
                    'step_applicable.'.self::STAP_EXTRA_ACTIVITEITEN => true,
                ],
            ]],

            'Lasershow toont specifiek inhoud-blok' => [[
                'naam' => 'Lasershow (A38) → contentLasershow-blok + stap actief',
                'omschrijving' => 'Bij een lasershow (activiteit A38) is er specifieke regelgeving. Het '
                    .'formulier toont een inhoud-blok daarover en markeert deze pagina als '
                    .'van toepassing.',
                'categorie' => 'visibility',
                'stap' => self::STAP_EXTRA_ACTIVITEITEN,
                'trigger_velden' => ['welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX'],
                'gegeven' => [
                    'welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX' => ['A38' => true],
                ],
                'verwacht' => [
                    'field_hidden.contentLasershow' => false,
                    'step_applicable.'.self::STAP_EXTRA_ACTIVITEITEN => true,
                ],
            ]],
        ];
    }
}
