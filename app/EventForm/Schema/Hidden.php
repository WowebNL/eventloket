<?php

declare(strict_types=1);

namespace App\EventForm\Schema;

use Closure;

/**
 * Closure-factory voor `->hidden()` op form-componenten die hun
 * zichtbaarheid uit `FormFieldVisibility` halen.
 *
 * Vervangt het overal herhaalde
 *   ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('X') !== false)
 * door
 *   ->hidden(Hidden::rule('X'))
 *
 * `isFieldHidden()` geeft true/false/null terug: een expliciete `false`
 * betekent "tonen", al het andere (true, of null = val-door naar de
 * default-zichtbaarheid uit de step-file) verbergt het veld. Die `!== false`-
 * semantiek staat nu op één plek i.p.v. ~75× gekopieerd.
 */
final class Hidden
{
    public static function rule(string $field): Closure
    {
        return static fn ($livewire): bool => $livewire->state()->isFieldHidden($field) !== false;
    }
}
