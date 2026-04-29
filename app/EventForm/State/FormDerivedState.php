<?php

declare(strict_types=1);

namespace App\EventForm\State;

/**
 * Pure-functions-class voor afgeleide variabelen. Vervangt stuk voor
 * stuk de `setVariable`-acties uit de gegenereerde rule-classes door
 * lazy-computed accessors.
 *
 * Werking:
 *   - Elke methode is naam-equivalent aan z'n OF-variabele-naam
 *     (`evenementInGemeentenNamen()` ↔ `'evenementInGemeentenNamen'`).
 *   - `FormState::get()` raadpleegt deze class als de gevraagde key
 *     overeenkomt met een gemigreerde derivatie. Zo blijven templates
 *     en rules-die-nog-niet-gemigreerd-zijn unchanged werken — ze
 *     krijgen automatisch de gecomputeerde waarde.
 *   - Methodes lezen ALLEEN uit de meegegeven `FormState`. Geen side-
 *     effects, geen DB-calls, geen HTTP. Idempotent en goedkoop te
 *     re-evalueren bij elke render.
 *
 * Migratie-regel: als een variabele hier een methode heeft, mag de
 * oude rule die dezelfde naam schreef worden verwijderd. Tijdens de
 * overgang kunnen beide naast elkaar bestaan — FormDerivedState
 * wint dankzij de delegatie in `FormState::get()`.
 */
final class FormDerivedState
{
    /** @var array<string, true> */
    public const COMPUTED_KEYS = [
        'evenementInGemeentenNamen' => true,
    ];

    public function __construct(private readonly FormState $state) {}

    /**
     * Lijst van gemeente-namen die het ingetekende formulier raakt
     * (polygons + lijnen + adressen, gecombineerd via
     * `LocationServerCheckService` in `inGemeentenResponse.all.items`).
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('evenementInGemeentenNamen', map(items, 'name'))`
     *
     * @return list<string>
     */
    public function evenementInGemeentenNamen(): array
    {
        $items = $this->state->get('inGemeentenResponse.all.items');
        if (! is_array($items)) {
            return [];
        }

        $names = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                $names[] = $item['name'];
            }
        }

        return $names;
    }

    /**
     * Roept de juiste methode aan voor een gemigreerde key. Leeg
     * resultaat als de key (nog) niet gemigreerd is.
     */
    public function get(string $key): mixed
    {
        return match ($key) {
            'evenementInGemeentenNamen' => $this->evenementInGemeentenNamen(),
            default => null,
        };
    }
}
