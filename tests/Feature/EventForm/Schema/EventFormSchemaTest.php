<?php

declare(strict_types=1);

use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\AanvullendeVragenStep;
use App\EventForm\Schema\Steps\BijlagenStep;
use Filament\Schemas\Components\Wizard\Step;

test('EventFormSchema::steps() returns all 19 steps (17 OF-stappen + Aanvullende vragen + Samenvatting)', function () {
    // Sinds E.1 is er een hand-geschreven Samenvatting-stap toegevoegd
    // tussen Bijlagen en Type-aanvraag, met de verplichte AVG-akkoord-
    // checkbox en het overzicht van alle ingevulde data. Daarna kwam
    // "Aanvullende vragen" erbij, vlak voor Bijlagen.
    $steps = EventFormSchema::steps();

    expect($steps)->toHaveCount(19)
        ->and($steps[0])->toBeInstanceOf(Step::class);
});

test('EventFormSchema::steps() laat "Aanvullende vragen" weg wanneer gevraagd', function () {
    $steps = EventFormSchema::steps(null, withAanvullendeVragen: false);
    $labels = collect($steps)->map(fn (Step $s) => $s->getLabel())->all();

    expect($steps)->toHaveCount(18)
        ->and($labels)->not->toContain('Aanvullende vragen');
});

test('stepUuidsInOrder() volgt dezelfde conditie als steps()', function () {
    // Lopen die twee uiteen, dan wijst de afgeleide 1-based positie in
    // EventFormPage::resolveStartStep() één stap verkeerd.
    foreach ([true, false] as $withAanvullendeVragen) {
        $steps = EventFormSchema::steps(null, $withAanvullendeVragen);
        $uuids = EventFormSchema::stepUuidsInOrder($withAanvullendeVragen);

        expect($uuids)->toHaveCount(count($steps));
    }

    expect(EventFormSchema::stepUuidsInOrder())->toContain(AanvullendeVragenStep::UUID)
        ->and(EventFormSchema::stepUuidsInOrder(false))->not->toContain(AanvullendeVragenStep::UUID);
});

test('"Aanvullende vragen" staat direct vóór Bijlagen', function () {
    $uuids = EventFormSchema::stepUuidsInOrder();

    $extra = array_search(AanvullendeVragenStep::UUID, $uuids, true);
    $bijlagen = array_search(BijlagenStep::UUID, $uuids, true);

    expect($extra)->toBe($bijlagen - 1);
});

test('EventFormSchema::stepsForReport() bevat data-stappen + Type-aanvraag (zonder Samenvatting)', function () {
    // Samenvatting valt eruit omdat 'ie zichzelf rendert via deze
    // lijst — recursie. Type-aanvraag zit er WEL in: SubmissionReport
    // herkent 'm en bouwt zelf een afgeleide "Onderdelen aanvraag"-
    // sectie zodat 'ie ook in de samenvatting + PDF verschijnt.
    $report = EventFormSchema::stepsForReport();
    $labels = collect($report)->map(fn (Step $s) => $s->getLabel())->all();

    expect($report)->toHaveCount(18)
        ->and($labels)->not->toContain('Samenvatting')
        ->and($labels)->toContain('Aanvraag');
});

test('each step has a non-empty label', function () {
    foreach (EventFormSchema::steps() as $step) {
        expect($step->getLabel())->not->toBeNull()
            ->and($step->getLabel())->not->toBe('');
    }
});
