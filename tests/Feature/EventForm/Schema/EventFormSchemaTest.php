<?php

declare(strict_types=1);

use App\EventForm\Schema\EventFormSchema;
use Filament\Schemas\Components\Wizard\Step;

test('EventFormSchema::steps() returns all 18 steps (17 OF-stappen + Samenvatting)', function () {
    // Sinds E.1 is er een hand-geschreven Samenvatting-stap toegevoegd
    // tussen Bijlagen en Type-aanvraag, met de verplichte AVG-akkoord-
    // checkbox en het overzicht van alle ingevulde data.
    $steps = EventFormSchema::steps();

    expect($steps)->toHaveCount(18)
        ->and($steps[0])->toBeInstanceOf(Step::class);
});

test('EventFormSchema::stepsForReport() bevat data-stappen + Type-aanvraag (zonder Samenvatting)', function () {
    // Samenvatting valt eruit omdat 'ie zichzelf rendert via deze
    // lijst — recursie. Type-aanvraag zit er WEL in: SubmissionReport
    // herkent 'm en bouwt zelf een afgeleide "Onderdelen aanvraag"-
    // sectie zodat 'ie ook in de samenvatting + PDF verschijnt.
    $report = EventFormSchema::stepsForReport();
    $labels = collect($report)->map(fn (Step $s) => $s->getLabel())->all();

    expect($report)->toHaveCount(17)
        ->and($labels)->not->toContain('Samenvatting')
        ->and($labels)->toContain('Type aanvraag');
});

test('each step has a non-empty label', function () {
    foreach (EventFormSchema::steps() as $step) {
        expect($step->getLabel())->not->toBeNull()
            ->and($step->getLabel())->not->toBe('');
    }
});
