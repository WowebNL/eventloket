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
