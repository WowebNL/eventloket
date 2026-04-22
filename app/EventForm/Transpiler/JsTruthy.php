<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

/**
 * JS/JsonLogic-truthy semantiek, expres anders dan PHP's `(bool)`-cast.
 *
 * In JavaScript (en dus JsonLogic, de taal van OF-rules) gelden de volgende
 * waarden als falsy: `null`, `undefined`, `0`, `NaN`, `""` (empty string)
 * en `false`. Alle andere waarden zijn truthy — inclusief `"0"` (non-empty
 * string) en `"false"`. PHP's `(bool)`-cast behandelt juist `"0"` als false,
 * wat een subtiele maar vervelende afwijking oplevert bij rule-triggers als
 * `{"!!": [{"var": "veld"}]}` op radio-waarden als "0".
 *
 * Deze helper wordt aangeroepen door de geëmit-te code in
 * `app/EventForm/Rules/*.php` zodat de rules zich precies als OF gedragen.
 */
final class JsTruthy
{
    public static function of(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }
        if ($value === 0 || $value === 0.0) {
            return false;
        }
        if ($value === '') {
            return false;
        }
        if (is_array($value)) {
            // JsonLogic-js behandelt lege array als falsy.
            return $value !== [];
        }

        // Alle andere waarden (incl. string "0", negatieve getallen, objects) → truthy.
        return true;
    }
}
