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

test('EventFormSchema::stepsForReport() bevat alleen de data-collecterende stappen', function () {
    // De Samenvatting + Type-aanvraag tonen alleen data en bevatten geen
    // velden om in een rapport op te nemen — vandaar deze splitsing.
    $report = EventFormSchema::stepsForReport();
    $labels = collect($report)->map(fn (Step $s) => $s->getLabel())->all();

    expect($report)->toHaveCount(16)
        ->and($labels)->not->toContain('Samenvatting')
        ->and($labels)->not->toContain('Type aanvraag');
});

test('each step has a non-empty label', function () {
    foreach (EventFormSchema::steps() as $step) {
        expect($step->getLabel())->not->toBeNull()
            ->and($step->getLabel())->not->toBe('');
    }
});
