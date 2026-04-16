<?php

declare(strict_types=1);

use App\EventForm\Schema\EventFormSchema;
use Filament\Schemas\Components\Wizard\Step;

test('EventFormSchema::steps() returns all 17 steps instantiated', function () {
    $steps = EventFormSchema::steps();

    expect($steps)->toHaveCount(17)
        ->and($steps[0])->toBeInstanceOf(Step::class);
});

test('each step has a non-empty label', function () {
    foreach (EventFormSchema::steps() as $step) {
        expect($step->getLabel())->not->toBeNull()
            ->and($step->getLabel())->not->toBe('');
    }
});
