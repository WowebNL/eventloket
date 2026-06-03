<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 4: Tijden.
 *
 * De Tijden-stap laat de organisator de start- en eindtijden van het
 * evenement invullen. Rond deze stap bestaat één dynamisch element: als
 * de gemeente bekend maakt dat er andere evenementen gelijktijdig gepland
 * staan, wordt een inhoud-blok met die informatie getoond.
 */
final class TijdenScenarios implements ScenarioProvider
{
    public const STAP_TIJDEN = '00f09aee-fedd-44d6-b82c-3e3754d67b7a';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Waarschuwing voor gelijktijdig geplande evenementen';
    }

    public static function inleiding(): string
    {
        return 'Als de gemeente heeft gemeld dat er andere evenementen op dezelfde '
            .'datum staan, toont het formulier een waarschuwings-blok op de Tijden-'
            .'pagina. Zo kan de organisator zien of 8n planning-wijziging overwogen '
            .'moet worden.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Waarschuwing zichtbaar als er andere evenementen zijn' => [[
                'naam' => 'Waarschuwing over gelijktijdige evenementen verschijnt als er andere evenementen bekend zijn',
                'omschrijving' => 'Zodra evenementenInDeGemeente een (niet-lege) waarde heeft — dat wil zeggen: '
                    .'de service EventsCheckService heeft evenementen teruggekregen voor de gekozen '
                    .'datum — toont de Tijden-pagina een inhoud-blok dat de organisator waarschuwt '
                    .'dat er al andere evenementen gepland staan.',
                'categorie' => 'visibility',
                'stap' => self::STAP_TIJDEN,
                'trigger_velden' => ['evenementenInDeGemeente'],
                'gegeven' => [
                    'evenementenInDeGemeente' => 'Koningsdag-markt, Kermis Centrum',
                ],
                'verwacht' => [
                    'field_hidden.evenmentenInDeBuurtContent' => false,
                ],
            ]],
        ];
    }
}
