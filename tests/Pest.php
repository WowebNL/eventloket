<?php

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Walk a wizard step recursively and collect its date time pickers by field
 * name. The fields are nested inside layout components, and reading them back
 * needs the raw `childComponents` array: `getChildComponents()` requires a
 * container, which an isolated test does not have.
 *
 * This lives here rather than in a test file because more than one test file
 * uses it, and a helper defined in a test file only exists in the process that
 * loaded that file. Under a parallel run the other file lands in a different
 * worker and the call fails on an undefined function.
 *
 * @return array<string, DateTimePicker>
 */
function findDateTimePickers(Step $step): array
{
    $found = [];
    $walk = function (object $component) use (&$walk, &$found): void {
        if ($component instanceof DateTimePicker) {
            $found[$component->getName()] = $component;
        }

        // The raw `childComponents` array holds the schema arrays as they were
        // passed to `->schema([...])`, so each entry is a plain PHP array of
        // child components.
        if (! property_exists($component, 'childComponents')) {
            return;
        }
        $reflection = new ReflectionObject($component);
        $childProp = $reflection->getProperty('childComponents');
        $childProp->setAccessible(true);
        $children = $childProp->getValue($component);
        foreach ($children as $componentList) {
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

function something()
{
    // ..
}
