<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 2: Het evenement.
 *
 * Op deze pagina staan geen OF-rules, maar wel component-level conditionals:
 * velden worden pas zichtbaar zodra een eerder veld is ingevuld of een
 * bepaalde waarde heeft. Die gedragsregels zitten op het Filament-component
 * zelf via `->hidden()`-closures die de huidige form-state lezen.
 *
 * Deze scenarios bewijzen dat die conditionele zichtbaarheid net zo werkt
 * als in Open Forms — door via de echte Livewire-page de trigger-waarden
 * te zetten en te controleren of de target-velden daadwerkelijk
 * gerenderd worden.
 */
final class HetEvenementScenarios implements ScenarioProvider
{
    public const STAP_HET_EVENEMENT = 'c3c17c65-0cf1-4a79-a348-75eab01f46ec';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Conditionele zichtbaarheid op stap "Het evenement"';
    }

    public static function inleiding(): string
    {
        return 'Op deze stap bepalen een paar velden of vervolgvragen te zien zijn. '
            .'Zodra de evenementnaam is ingevuld, verschijnen omschrijving- en '
            .'soort-velden. Bij "Anders" als soort komt er een extra tekstveld vrij. '
            .'Bij "Markt of braderie" komt er een periodieke-markt-vraag vrij.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Evenementnaam ingevuld → omschrijving + soort zichtbaar' => [[
                'naam' => 'Evenementnaam ingevuld → omschrijving + soort-veld verschijnen',
                'omschrijving' =>
                    'Zolang "Wat is de naam van het evenement?" leeg is, hoeven de '
                    .'vervolgvelden niet in beeld. Zodra de gebruiker een naam heeft '
                    .'ingevuld, komen "Geef een korte omschrijving" en "Wat voor soort '
                    .'evenement is het?" tevoorschijn.',
                'categorie' => 'visibility',
                'stap' => self::STAP_HET_EVENEMENT,
                'trigger_velden' => ['watIsDeNaamVanHetEvenementVergunning'],
                'gegeven' => [
                    'watIsDeNaamVanHetEvenementVergunning' => 'Zomerfestival 2026',
                ],
                'verwacht' => [
                    'field_visible.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => true,
                    'field_visible.soortEvenement' => true,
                ],
            ]],

            'Soort = Anders → omschrijving-veld verschijnt' => [[
                'naam' => 'Soort evenement "Anders" → omschrijf-veld verschijnt',
                'omschrijving' =>
                    'Als de gebruiker bij "Wat voor soort evenement?" kiest voor '
                    .'"Anders", komt een extra tekstveld "Omschrijf het soort '
                    .'evenement" tevoorschijn waar een eigen omschrijving gevraagd '
                    .'wordt.',
                'categorie' => 'visibility',
                'stap' => self::STAP_HET_EVENEMENT,
                'trigger_velden' => ['soortEvenement'],
                'gegeven' => [
                    'watIsDeNaamVanHetEvenementVergunning' => 'Wandeltocht',
                    'soortEvenement' => 'Anders',
                ],
                'verwacht' => [
                    'field_visible.omschrijfHetSoortEvenement' => true,
                ],
            ]],

            'Soort = Markt → periodieke-markt-vraag verschijnt' => [[
                'naam' => 'Soort evenement "Markt of braderie" → periodiciteit-vraag verschijnt',
                'omschrijving' =>
                    'Bij een markt of braderie moet de organisator aangeven of het '
                    .'gaat om een periodiek terugkerende markt (jaar/week-markt) '
                    .'waarvoor de gemeente al een regulier besluit heeft.',
                'categorie' => 'visibility',
                'stap' => self::STAP_HET_EVENEMENT,
                'trigger_velden' => ['soortEvenement'],
                'gegeven' => [
                    'watIsDeNaamVanHetEvenementVergunning' => 'Weekmarkt Maastricht',
                    'soortEvenement' => 'Markt of braderie',
                ],
                'verwacht' => [
                    'field_visible.gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen' => true,
                ],
            ]],
        ];
    }
}
