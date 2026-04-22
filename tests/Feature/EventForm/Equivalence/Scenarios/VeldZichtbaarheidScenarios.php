<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider: velden en stappen die zichtbaar worden op basis van
 * keuzes eerder in het formulier.
 */
final class VeldZichtbaarheidScenarios implements ScenarioProvider
{
    public const STAP_VERGUNNINGSAANVRAAG_EXTRA_ACTIVITEITEN = '661aabb7-e927-4a75-8d95-0a665c5d83fe';

    public const STAP_VERGUNNINGSAANVRAAG_VOORZIENINGEN = 'f4e91db5-fd74-4eba-b818-96ed2cc07d84';

    public const STAP_VERGUNNINGSAANVRAAG_VOORWERPEN = 'd790edb5-712a-4f83-87a8-1a86e4831455';

    public const STAP_CONTACTGEGEVENS = '48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Conditionele zichtbaarheid van velden en stappen';
    }

    public static function inleiding(): string
    {
        return 'Veel velden in het formulier zijn pas relevant als de organisator een '
            .'specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook '
            .'een volledige stap in de wizard-sidebar. Een fout hier betekent dat de '
            .'gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet '
            .'die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende '
            .'aanvragen.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Bouwsels groter dan 10 m² — velden en stap zichtbaar na aanvinken' => [[
                'naam' => 'Bouwsels >10 m² — velden en stap zichtbaar na aanvinken',
                'omschrijving' =>
                    'Als de organisator bij "wat is van toepassing voor uw evenement" de optie A3 '
                    .'(bouwsels groter dan 10 m²) aanvinkt, moeten de vervolg-velden zichtbaar worden '
                    .'en wordt de stap "Vergunningsaanvraag: extra activiteiten" in de sidebar '
                    .'actief.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VERGUNNINGSAANVRAAG_EXTRA_ACTIVITEITEN,
                'trigger_velden' => ['kruisAanWatVanToepassingIsVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A3' => true],
                ],
                'verwacht' => [
                    'field_hidden.bouwsels10MSup2Sup' => false,
                    'field_hidden.watVoorBouwselsPlaatsUOpDeLocaties' => false,
                    'step_applicable.'.self::STAP_VERGUNNINGSAANVRAAG_EXTRA_ACTIVITEITEN => true,
                ],
            ]],

            'Speeltoestellen — voorwerpen-stap van toepassing na A25' => [[
                'naam' => 'Speeltoestellen — voorwerpen-stap van toepassing na A25',
                'omschrijving' =>
                    'Als de organisator aangeeft speeltoestellen te plaatsen (optie A25 in '
                    .'"welke voorwerpen gaat u plaatsen"), moeten "Speeltoestellen" en "voorwerpen" '
                    .'zichtbaar zijn én wordt de stap "Vergunningsaanvraag: voorwerpen" actief.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VERGUNNINGSAANVRAAG_VOORWERPEN,
                'trigger_velden' => ['welkeVoorwerpenGaatUPlaatsenBijUwEvenementX'],
                'gegeven' => [
                    'welkeVoorwerpenGaatUPlaatsenBijUwEvenementX' => ['A25' => true],
                ],
                'verwacht' => [
                    'field_hidden.Speeltoestellen' => false,
                    'field_hidden.voorwerpen' => false,
                    'step_applicable.'.self::STAP_VERGUNNINGSAANVRAAG_VOORWERPEN => true,
                ],
            ]],

            'KvK-gebruiker — adresgegevens verborgen' => [[
                'naam' => 'KvK-gebruiker — adresgegevens verborgen',
                'omschrijving' =>
                    'Gebruiker ingelogd via eHerkenning/KvK heeft de organisatie-gegevens '
                    .'al uit de KvK-koppeling. "Organisatie-informatie" wordt zichtbaar om '
                    .'de opgehaalde gegevens te tonen; "Adresgegevens" wordt verborgen omdat '
                    .'het adres al bekend is.',
                'categorie' => 'visibility',
                'stap' => self::STAP_CONTACTGEGEVENS,
                'trigger_velden' => ['eventloketSession.kvk'],
                'gegeven' => [
                    'eventloketSession.kvk' => '12345678',
                ],
                'verwacht' => [
                    'field_hidden.organisatieInformatie' => false,
                    'field_hidden.adresgegevens' => true,
                ],
            ]],
        ];
    }
}
