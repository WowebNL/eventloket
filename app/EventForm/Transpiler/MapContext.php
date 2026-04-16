<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

use App\EventForm\State\FormState;

/**
 * Helper die binnen een `map`-expressie als `FormState` poseert zodat `var`-
 * lookups op het huidige iteratie-item landen. Is geen vervanging voor de
 * eigenlijke FormState — alleen een transient read-only view tijdens de map.
 */
class MapContext extends FormState
{
    /**
     * @param  array<string, mixed>|object  $item  het huidige item uit de
     *                                             map-array; veld-waarden
     *                                             hierop worden eerst
     *                                             geraadpleegd.
     */
    public static function from(FormState $outer, mixed $item): self
    {
        $values = is_array($item) ? $item : [];
        if (is_object($item)) {
            foreach (get_object_vars($item) as $k => $v) {
                $values[$k] = $v;
            }
        }

        return new self(values: $values);
    }
}
