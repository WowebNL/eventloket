<?php

declare(strict_types=1);

/**
 * Issue #24: for a multi-day event the organiser must be able to give a start
 * and end time per day, for the build-up and tear-down as well.
 *
 * This test guards that the three day repeaters are present in the rendered
 * step and carry the right fields. The rules themselves (what "multi-day"
 * means, how a night rolls over) live in the unit tests of EventDagen and
 * DagenRepeater.
 */

use App\EventForm\Schema\Steps\TijdenStep;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Wizard\Step;

function findRepeaters(Step $step): array
{
    $found = [];
    $walk = function (object $component) use (&$walk, &$found): void {
        if ($component instanceof Repeater) {
            $found[$component->getName()] = $component;
        }

        if (! property_exists($component, 'childComponents')) {
            return;
        }

        $reflection = new ReflectionObject($component);
        $childProp = $reflection->getProperty('childComponents');
        $childProp->setAccessible(true);

        foreach ($childProp->getValue($component) as $componentList) {
            if (! is_array($componentList)) {
                continue;
            }
            foreach ($componentList as $child) {
                if (is_object($child)) {
                    $walk($child);
                }
            }
        }
    };
    $walk($step);

    return $found;
}

test('de tijden-stap heeft een dag-repeater voor evenement, opbouw en afbouw', function () {
    $repeaters = findRepeaters(TijdenStep::make());

    expect($repeaters)->toHaveKeys(['EvenementDagen', 'OpbouwDagen', 'AfbouwDagen']);
});

test('elke dag-repeater vraagt een start- en een eindtijd', function (string $key) {
    $repeater = findRepeaters(TijdenStep::make())[$key];

    $reflection = new ReflectionObject($repeater);
    $childProp = $reflection->getProperty('childComponents');
    $childProp->setAccessible(true);

    $tijdVelden = [];
    foreach ($childProp->getValue($repeater) as $componentList) {
        foreach ((array) $componentList as $child) {
            if ($child instanceof TimePicker) {
                $tijdVelden[] = $child->getName();
            }
        }
    }

    expect($tijdVelden)->toBe(['startTijd', 'eindTijd']);
})->with(['EvenementDagen', 'OpbouwDagen', 'AfbouwDagen']);

test('dagregels kunnen niet met de hand toegevoegd of verwijderd worden', function (string $key) {
    // Rows follow the date span of the two pickers above; adding rows by hand
    // would produce a day outside that period.
    $repeater = findRepeaters(TijdenStep::make())[$key];

    // The public isAddable()/isDeletable() need a container that an isolated
    // step does not have, so read the flags directly.
    $reflection = new ReflectionObject($repeater);
    $lees = function (string $property) use ($reflection, $repeater) {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($repeater);
    };

    expect($lees('isAddable'))->toBeFalse();
    expect($lees('isDeletable'))->toBeFalse();
    expect($lees('isReorderable'))->toBeFalse();
})->with(['EvenementDagen', 'OpbouwDagen', 'AfbouwDagen']);
