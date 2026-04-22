<?php

declare(strict_types=1);

/**
 * Gedrags-equivalentietest: velden die zichtbaar worden op basis van
 * keuzes op andere velden, plus de bijbehorende stappen die daarmee
 * "van toepassing" worden verklaard.
 *
 * ─── Wat wordt hier getest? ──────────────────────────────────────────────
 * In het evenementformulier zijn veel velden pas relevant als de gebruiker
 * een bepaalde keuze maakt op een eerder veld. Bijvoorbeeld:
 *
 *   "Als ik bij 'wat is van toepassing?' de optie 'bouwsels > 10 m²' aanvink,
 *    dan moeten velden over bouwsels zichtbaar worden én moet de stap met
 *    extra activiteiten als van toepassing worden gemarkeerd in de sidebar."
 *
 * Dit soort conditionele zichtbaarheid was in Open Forms geregeld via 144
 * logic-rules + conditional-metadata op componenten. In de nieuwe Filament-
 * versie gebeurt hetzelfde via de RulesEngine (nu) en straks via native
 * Filament `->visible(fn (Get $get) => ...)` closures.
 *
 * Een fout hier betekent dat gebruikers velden niet te zien krijgen die ze
 * zouden moeten invullen, of omgekeerd velden zien die ze niet mogen
 * beantwoorden. Beide geven verkeerde aanvragen.
 *
 * ─── Hoe werkt deze test? ───────────────────────────────────────────────
 * Per scenario beschrijven we in mensentaal: "gegeven dat de gebruiker X
 * aanvinkt, verwachten we dat veld Y zichtbaar is en stap Z van toepassing
 * is". De test zet de input, draait de RulesEngine, en controleert de
 * resulterende zichtbaarheid- en stap-applicatie-flags.
 */

use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;

/**
 * UUIDs van stappen waar deze tests naar verwijzen. Pinnen we expliciet
 * vast zodat de testnamen leesbaar blijven en een gefaalde test duidelijk
 * terugverwijst naar welke stap het betreft.
 */
const STAP_VERGUNNINGSAANVRAAG_EXTRA_ACTIVITEITEN = '661aabb7-e927-4a75-8d95-0a665c5d83fe';
const STAP_VERGUNNINGSAANVRAAG_VOORZIENINGEN = 'f4e91db5-fd74-4eba-b818-96ed2cc07d84';
const STAP_VERGUNNINGSAANVRAAG_VOORWERPEN = 'd790edb5-712a-4f83-87a8-1a86e4831455';

/**
 * @return array<string, array<int, array<string, mixed>>>
 */
function veldZichtbaarheidScenarios(): array
{
    return [
        'Bouwsels >10m² zichtbaar als A3 aangevinkt' => [[
            'naam' => 'Bouwsels groter dan 10 m² — velden zichtbaar na aanvinken',
            'omschrijving' =>
                'Wanneer de organisator bij "wat is van toepassing voor uw evenement" '.
                'de optie A3 (bouwsels groter dan 10 m²) aanvinkt, moet het veld '.
                '"bouwsels10MSup2Sup" zichtbaar worden plus het vervolgveld waar '.
                'gevraagd wordt welke bouwsels geplaatst worden. Tegelijk wordt de '.
                'stap "vergunningsaanvraag: extra activiteiten" van toepassing.',
            'gegeven' => [
                'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A3' => true],
            ],
            'verwacht' => [
                'field_hidden.bouwsels10MSup2Sup' => false,
                'field_hidden.watVoorBouwselsPlaatsUOpDeLocaties' => false,
                'step_applicable.'.STAP_VERGUNNINGSAANVRAAG_EXTRA_ACTIVITEITEN => true,
            ],
        ]],

        'Speeltoestellen zichtbaar als A25 aangevinkt' => [[
            'naam' => 'Speeltoestellen — voorwerpen-stap van toepassing na A25',
            'omschrijving' =>
                'Als de organisator aangeeft speeltoestellen te plaatsen (optie A25 '.
                'in "welke voorwerpen gaat u plaatsen"), dan moeten zowel het veld '.
                '"Speeltoestellen" als de vervolgvraag "voorwerpen" zichtbaar zijn '.
                'én wordt de stap "vergunningsaanvraag: voorwerpen" van toepassing.',
            'gegeven' => [
                'welkeVoorwerpenGaatUPlaatsenBijUwEvenementX' => ['A25' => true],
            ],
            'verwacht' => [
                'field_hidden.Speeltoestellen' => false,
                'field_hidden.voorwerpen' => false,
                'step_applicable.'.STAP_VERGUNNINGSAANVRAAG_VOORWERPEN => true,
            ],
        ]],

        'Organisatie-informatie verborgen voor KvK-login' => [[
            'naam' => 'KvK-gebruiker hoeft eigen KvK-gegevens niet in te vullen',
            'omschrijving' =>
                'Als de gebruiker via eHerkenning/KvK ingelogd is — herkenbaar aan '.
                'een waarde in eventloketSession.kvk — dan is de organisatie-info '.
                'al bekend. Het veld "organisatieInformatie" moet zichtbaar '.
                'worden om te laten zien welke gegevens zijn opgehaald; "adresgegevens" '.
                'moet juist verborgen zijn omdat het adres al uit KvK komt.',
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

test(
    'Veld-zichtbaarheid volgt de gebruikers-keuzes zoals in Open Forms: {0.naam}',
    function (array $scenario) {
        $diffs = EquivalenceScenario::run($scenario);

        expect($diffs)->toBe(
            [],
            sprintf(
                "Scenario faalt — %s\n\nOmschrijving: %s\n\nAfwijkingen: %s",
                $scenario['naam'],
                $scenario['omschrijving'],
                json_encode($diffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ),
        );
    },
)->with(veldZichtbaarheidScenarios());
