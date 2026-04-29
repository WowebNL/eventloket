<?php

declare(strict_types=1);

/**
 * `LocatiePolygonsPatch::apply()` haalt de Repeater rond
 * `locatieSOpKaart` weg en zet er één TextInput + één Map voor in de
 * plaats. Een patch i.p.v. een bewerking van de step-file zelf, zodat
 * de transpiler-output (`app/EventForm/Schema/Steps/...`) door
 * `transpile:event-form` mag worden geregenereerd zonder dat we deze
 * structurele wijziging telkens kwijtraken.
 */

use App\EventForm\Schema\Patches\LocatiePolygonsPatch;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard\Step;

/**
 * Pak via reflection de `default`-childComponents van een Step. Filament's
 * `getChildComponents()` verlangt een mounted container die we hier niet
 * hebben.
 *
 * @return list<object>
 */
function locatieChildComponents(Step $step): array
{
    $ref = new ReflectionObject($step);
    $prop = $ref->getProperty('childComponents');
    $prop->setAccessible(true);
    $children = $prop->getValue($step);

    return is_array($children) && is_array($children['default'] ?? null) ? $children['default'] : [];
}

test('na de patch is er geen Repeater meer rond locatieSOpKaart', function () {
    $patched = LocatiePolygonsPatch::apply(LocatieVanHetEvenement2Step::make());
    $children = locatieChildComponents($patched);

    foreach ($children as $component) {
        expect(
            $component instanceof Repeater && $component->getName() === 'locatieSOpKaart'
        )->toBeFalse('Repeater op locatieSOpKaart had weg moeten zijn');
    }
});

test('na de patch is locatieSOpKaart een Map en naamVanDeLocatieKaart een TextInput naast elkaar', function () {
    $patched = LocatiePolygonsPatch::apply(LocatieVanHetEvenement2Step::make());
    $children = locatieChildComponents($patched);

    $heeftNaam = collect($children)->contains(
        fn ($c) => $c instanceof TextInput && $c->getName() === 'naamVanDeLocatieKaart'
    );
    $heeftMap = collect($children)->contains(
        fn ($c) => $c instanceof Map && $c->getName() === 'locatieSOpKaart'
    );

    expect($heeftNaam)->toBeTrue('TextInput naamVanDeLocatieKaart ontbreekt')
        ->and($heeftMap)->toBeTrue('Map locatieSOpKaart ontbreekt');
});

test('andere componenten op de stap (bv. waarVindtHetEvenementPlaats) blijven gewoon staan', function () {
    $patched = LocatiePolygonsPatch::apply(LocatieVanHetEvenement2Step::make());
    $children = locatieChildComponents($patched);

    $namen = collect($children)
        ->filter(fn ($c) => method_exists($c, 'getName'))
        ->map(fn ($c) => $c->getName())
        ->all();

    expect($namen)->toContain('waarVindtHetEvenementPlaats')
        ->and($namen)->toContain('adresVanDeGebouwEn')
        ->and($namen)->toContain('userSelectGemeente');
});
