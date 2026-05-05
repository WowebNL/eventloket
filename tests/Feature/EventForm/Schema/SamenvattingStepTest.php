<?php

declare(strict_types=1);

/**
 * SamenvattingStep is een hand-geschreven wizard-stap die vóór de
 * Type-aanvraag-stap komt. Twee taken:
 *
 *  1. Toon alle ingevulde waarden gegroepeerd per OF-stap (zelfde
 *     opmaak als de submission-PDF).
 *  2. Verplicht een AVG-akkoord-checkbox voordat de organisator kan
 *     indienen — privacy-compliance vereist explicit consent.
 */

use App\EventForm\Schema\CustomSteps\SamenvattingStep;
use App\EventForm\Schema\EventFormSchema;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Wizard\Step;

function samenvattingChildren(Step $step): array
{
    $ref = new ReflectionObject($step);
    $prop = $ref->getProperty('childComponents');
    $prop->setAccessible(true);
    $children = $prop->getValue($step);

    return is_array($children) && is_array($children['default'] ?? null) ? $children['default'] : [];
}

test('Samenvatting komt vlak vóór Type-aanvraag in de wizard', function () {
    $steps = EventFormSchema::steps();
    $labels = collect($steps)->map(fn (Step $s) => $s->getLabel())->all();
    $samenvattingIndex = array_search('Samenvatting', $labels, true);
    $typeAanvraagIndex = array_search('Type aanvraag', $labels, true);

    expect($samenvattingIndex)->toBeInt('Samenvatting-stap ontbreekt')
        ->and($typeAanvraagIndex)->toBeInt()
        ->and($typeAanvraagIndex - $samenvattingIndex)->toBe(1);
});

test('Samenvatting bevat een verplichte akkoord-checkbox', function () {
    $children = samenvattingChildren(SamenvattingStep::make());

    $checkbox = collect($children)->first(
        fn ($c) => $c instanceof Checkbox && $c->getName() === 'akkoordVerwerkingGegevens'
    );

    expect($checkbox)->not->toBeNull('akkoordVerwerkingGegevens-checkbox ontbreekt');

    // Required + accepted = uitsluitend `true` is een geldige waarde,
    // anders blokkeert validation de submit.
    $reflection = new ReflectionObject($checkbox);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($checkbox);

    $heeftAcceptedRule = collect($rules)->contains(function ($entry): bool {
        [$rule] = $entry;

        return is_string($rule) && $rule === 'accepted';
    });

    expect($heeftAcceptedRule)->toBeTrue('checkbox heeft geen accepted-rule, waardoor leeg laten zou doorglippen')
        ->and($checkbox->isRequired())->toBeTrue();
});
