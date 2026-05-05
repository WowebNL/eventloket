<?php

declare(strict_types=1);

namespace App\EventForm\Validation;

/**
 * Cross-field datumconstraints op de tijden-stap. Geen eigen logica —
 * we leunen op Filament's eigen `->after()/->before()/->afterOrEqual()/
 * ->beforeOrEqual()` die intern Laravel-rules `after`/`before`/etc.
 * gebruiken (vendor/filament/forms/src/Components/Concerns/CanBeValidated.php).
 *
 * De waarden zijn ruwe modifier-statements die de transpiler ongewijzigd
 * achter de DateTimePicker emit. We doen het via een constant zodat de
 * regels op één plek staan en bij re-transpile niet kwijt zijn.
 *
 * Samenhang van de tijden:
 *
 *     OpbouwStart ≤ OpbouwEind ≤ EvenementStart ≤ EvenementEind ≤ AfbouwStart ≤ AfbouwEind
 *
 * EvenementStart moet bovendien minimaal vandaag zijn — voorkomt dat een
 * organisator een aanvraag voor een evenement in het verleden indient.
 */
final class TijdenFieldRules
{
    /**
     * @var array<string, list<string>> Veld-key → list van modifier-statements
     *                                  die de transpiler na de basis-make()
     *                                  emit. Statements moeten zonder leading
     *                                  spaties zijn — de transpiler zorgt
     *                                  voor de juiste indent.
     */
    public const PER_FIELD = [
        'EvenementStart' => [
            "->afterOrEqual('today')",
        ],
        'EvenementEind' => [
            "->afterOrEqual('EvenementStart')",
        ],
        'OpbouwEind' => [
            "->beforeOrEqual('EvenementStart')",
        ],
        'OpbouwStart' => [
            "->beforeOrEqual('OpbouwEind')",
        ],
        'AfbouwStart' => [
            "->afterOrEqual('EvenementEind')",
        ],
        'AfbouwEind' => [
            "->afterOrEqual('AfbouwStart')",
        ],
    ];
}
