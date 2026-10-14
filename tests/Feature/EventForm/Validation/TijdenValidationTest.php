<?php

declare(strict_types=1);

/**
 * Cross-field validatie op de tijden-stap. De feedback van de
 * opdrachtgever was: "Het is nu mogelijk om een evenement-eind in te
 * vullen dat vóór de evenement-start ligt, of een opbouw-tijd die na de
 * evenement-start valt — dat hoort niet te kunnen."
 *
 * Onze fix: Filament's eigen `->afterOrEqual('AnderVeld')` /
 * `->beforeOrEqual('AnderVeld')` modifiers op de DateTimePickers in
 * TijdenStep, gegenereerd door de transpiler op basis van de constanten
 * in {@see TijdenFieldRules}. We checken hier
 * dat die regels daadwerkelijk op de gegenereerde stap-componenten
 * staan — als de transpiler stuk gaat, of als de modifier-list
 * onbedoeld leeg raakt, vangen deze tests dat meteen op.
 */

use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\Validation\TijdenFieldRules;

test('elk Tijden-veld in TijdenFieldRules komt terug als DateTimePicker in de gerenderde stap', function () {
    $pickers = findDateTimePickers(TijdenStep::make());

    foreach (array_keys(TijdenFieldRules::PER_FIELD) as $fieldKey) {
        expect(array_key_exists($fieldKey, $pickers))
            ->toBeTrue("Veld '{$fieldKey}' uit TijdenFieldRules ontbreekt in de gerenderde TijdenStep");
    }
});

test('EvenementEind moet op of na EvenementStart liggen — Filament-rule is geregistreerd', function () {
    // Filament's `afterOrEqual()` is een wrapper rond `->rule(Closure)`.
    // De geregistreerde Closure resolveert pas tijdens validatie tot een
    // string als `'after_or_equal:data.EvenementStart'`. We hoeven hier
    // alleen te bewijzen dat een rule met die "doel-veld"-referentie
    // geregistreerd is — niet de hele Laravel-validator-keten.
    $pickers = findDateTimePickers(TijdenStep::make());
    $eind = $pickers['EvenementEind'];

    $reflection = new ReflectionClass($eind);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($eind);

    // We zoeken een rule die — wanneer geëvalueerd — naar 'EvenementStart'
    // verwijst. Dat is de hand-getypte cross-field-modifier die wij
    // geregistreerd hebben in TijdenFieldRules.
    $heeftAfterOrEqualEvenementStart = collect($rules)->contains(function ($entry): bool {
        [$rule] = $entry;
        if (! $rule instanceof Closure) {
            return false;
        }
        // De Closure roept `->resolveRelativeStatePath(...)` op een Field.
        // We kunnen de Closure niet direct evalueren zonder een component-
        // context op te tuigen, maar we kunnen hem inspecteren via
        // ReflectionFunction en kijken naar de gebonden waarden.
        $rfn = new ReflectionFunction($rule);
        $vars = $rfn->getStaticVariables();

        return ($vars['rule'] ?? null) === 'after_or_equal'
            && ($vars['date'] ?? null) === 'EvenementStart';
    });

    expect($heeftAfterOrEqualEvenementStart)->toBeTrue();
});

test('OpbouwEind moet op of voor EvenementStart liggen — Filament-rule is geregistreerd', function () {
    $pickers = findDateTimePickers(TijdenStep::make());
    $opbouwEind = $pickers['OpbouwEind'];

    $reflection = new ReflectionClass($opbouwEind);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($opbouwEind);

    $heeftBeforeOrEqualEvenementStart = collect($rules)->contains(function ($entry): bool {
        [$rule] = $entry;
        if (! $rule instanceof Closure) {
            return false;
        }
        $vars = (new ReflectionFunction($rule))->getStaticVariables();

        return ($vars['rule'] ?? null) === 'before_or_equal'
            && ($vars['date'] ?? null) === 'EvenementStart';
    });

    expect($heeftBeforeOrEqualEvenementStart)->toBeTrue();
});

test('AfbouwStart moet op of na EvenementEind liggen — Filament-rule is geregistreerd', function () {
    $pickers = findDateTimePickers(TijdenStep::make());
    $afbouwStart = $pickers['AfbouwStart'];

    $reflection = new ReflectionClass($afbouwStart);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($afbouwStart);

    $heeftAfterOrEqualEvenementEind = collect($rules)->contains(function ($entry): bool {
        [$rule] = $entry;
        if (! $rule instanceof Closure) {
            return false;
        }
        $vars = (new ReflectionFunction($rule))->getStaticVariables();

        return ($vars['rule'] ?? null) === 'after_or_equal'
            && ($vars['date'] ?? null) === 'EvenementEind';
    });

    expect($heeftAfterOrEqualEvenementEind)->toBeTrue();
});

test('EvenementStart moet vandaag of later zijn — voorkomt dat we vergunningen voor het verleden aanvragen', function () {
    // Onderwerp: validatie + calendar-grijsmaak. Voorheen stond hier
    // de letterlijke string 'today'; die toonde Laravel ook letterlijk
    // in de error-melding. Nu geven we de actuele datum als ISO-string
    // mee, zodat de :date-placeholder een echte datum is en de NL-
    // override-melding ('vandaag of later') leesbaar overkomt.
    $pickers = findDateTimePickers(TijdenStep::make());
    $start = $pickers['EvenementStart'];

    $reflection = new ReflectionClass($start);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($start);

    $vandaag = today()->toDateString();
    $heeftAfterOrEqualToday = collect($rules)->contains(function ($entry) use ($vandaag): bool {
        [$rule] = $entry;
        if (! $rule instanceof Closure) {
            return false;
        }
        $vars = (new ReflectionFunction($rule))->getStaticVariables();

        return ($vars['rule'] ?? null) === 'after_or_equal'
            && ($vars['date'] ?? null) === $vandaag;
    });

    expect($heeftAfterOrEqualToday)->toBeTrue();
});
