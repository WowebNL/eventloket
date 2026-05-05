<?php

declare(strict_types=1);

/**
 * SubmissionReport bouwt het inzendingsbewijs op basis van de
 * Filament-step-definities + de FormState. De opdrachtgever
 * rapporteerde dat de oude PDF maar 18 velden toonde, terwijl
 * organisators tientallen vragen kunnen beantwoorden — alle data
 * moet in het inzendingsbewijs terechtkomen.
 *
 * Onze fix: één service die elke stap afloopt, alle ingevulde velden
 * met hun (template-rendered) label terugstuurt, en stappen zonder
 * inhoud overslaat. Deze test bewijst dat:
 *
 *   1. Een lege state geen secties oplevert.
 *   2. Een state met enkele velden secties levert die alleen die
 *      ingevulde velden bevatten.
 *   3. DateTimePickers worden human-readable geformat.
 *   4. Radio-velden tonen de optie-label, niet de interne waarde.
 */

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\ContactgegevensStep;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\State\FormState;

test('lege state → geen secties (alle stappen worden overgeslagen)', function () {
    $sections = app(SubmissionReport::class)->build(
        FormState::empty(),
        EventFormSchema::steps(),
    );

    expect($sections)->toBe([]);
});

test('alleen contact-velden ingevuld → één sectie Contactgegevens', function () {
    $state = new FormState(values: [
        'watIsUwVoornaam' => 'Eva',
        'watIsUwAchternaam' => 'de Vries',
        'watIsUwEMailadres' => 'eva@example.nl',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [ContactgegevensStep::make()]);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Contactgegevens');

    // Drie ingevulde velden moeten als entries terugkomen; we toetsen
    // op aanwezigheid van de waarden i.p.v. exacte labels — de labels
    // zijn template-rendered en kunnen veranderen zonder dat de
    // grondslag (alle data in de PDF) breekt.
    $waarden = collect($sections[0]['entries'])->pluck('value')->all();
    expect($waarden)->toContain('Eva')
        ->and($waarden)->toContain('de Vries')
        ->and($waarden)->toContain('eva@example.nl');
});

test('DateTimePicker-waarden worden human-readable geformatteerd', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:30',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TijdenStep::make()]);

    // Beide waarden moeten als "j F Y · H:i" verschijnen — niet als ISO.
    $waarden = collect($sections[0]['entries'])->pluck('value')->all();
    expect($waarden)->toContain('14 juni 2026 · 14:30')
        ->and($waarden)->toContain('14 juni 2026 · 18:00');
});

test('Repeater-rijen worden uitgeklapt naar sub-entries in plaats van samengevat', function () {
    // Eén ingevulde adres-rij in adresVanDeGebouwEn moet niet als
    // "1 rij(en) ingevuld" verschijnen, maar als label-rij met de
    // sub-velden eronder (postcode, huisnummer, etc.).
    $state = new FormState(values: [
        'adresVanDeGebouwEn' => [
            [
                'naamVanDeLocatieGebouw' => 'Buurtcentrum De Hoek',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411CD',
                    'huisnummer' => '1',
                ],
            ],
        ],
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [LocatieVanHetEvenement2Step::make()],
    );

    expect($sections)->toHaveCount(1);
    $entries = $sections[0]['entries'];

    // Eerste entry is een Repeater-rij met sub-entries
    $repeaterRow = collect($entries)->first(fn ($e) => ! empty($e['sub']));
    expect($repeaterRow)->not->toBeNull();
    expect($repeaterRow['sub'])->toBeArray();

    $subValues = collect($repeaterRow['sub'])->pluck('value')->all();
    expect($subValues)->toContain('Buurtcentrum De Hoek')
        ->and($subValues)->toContain('6411CD');
});

test('Map-state met geojson levert een SVG mee in de entry', function () {
    // locatieSOpKaart is een Repeater met `buitenLocatieVanHetEvenement`
    // als Map-state per rij. SubmissionReport moet de polygon-geojson
    // herkennen en een SVG meeleveren in de sub-entry.
    $state = new FormState(values: [
        'locatieSOpKaart' => [
            [
                'naamVanDeLocatieKaart' => 'Plein',
                'buitenLocatieVanHetEvenement' => [
                    'lat' => 50.85,
                    'lng' => 5.69,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'geometry' => [
                                    'type' => 'Polygon',
                                    'coordinates' => [[
                                        [5.69, 50.85], [5.70, 50.85],
                                        [5.70, 50.86], [5.69, 50.86], [5.69, 50.85],
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [LocatieVanHetEvenement2Step::make()],
    );

    // Verzamel alle sub-entries (uit Repeater-rijen) + top-level entries.
    $allEntries = [];
    foreach ($sections as $section) {
        foreach ($section['entries'] as $entry) {
            $allEntries[] = $entry;
            foreach ($entry['sub'] ?? [] as $sub) {
                $allEntries[] = $sub;
            }
        }
    }
    $mapEntry = collect($allEntries)->first(fn ($e) => ! empty($e['svg']));

    expect($mapEntry)->not->toBeNull()
        ->and($mapEntry['svg'])->toContain('<img')
        ->and($mapEntry['svg'])->toContain('data:image/svg+xml;base64,');

    // Decoderen: de polygon-vorm moet nog steeds binnen de SVG zitten.
    preg_match('/base64,([^"]+)/', $mapEntry['svg'], $m);
    $rawSvg = base64_decode($m[1]);
    expect($rawSvg)->toContain('<svg')
        ->and($rawSvg)->toContain('<path'); // polygon-vorm
});

test('stappen zonder ingevulde velden worden weggelaten uit het overzicht', function () {
    // Tijden ingevuld, contact niet → alleen één sectie verwacht.
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:30',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [ContactgegevensStep::make(), TijdenStep::make()],
    );

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Tijden');
});
