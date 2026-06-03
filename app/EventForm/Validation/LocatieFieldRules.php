<?php

declare(strict_types=1);

namespace App\EventForm\Validation;

use App\EventForm\Rules\AlsBoolEnIsNietGelijkAanNone580a3ef8;
use App\EventForm\Rules\AlsReductieVan1BeginnendBij0IsGroterD;

/**
 * Hand-getypte modifier-statements voor velden op de Locatie-stap die
 * de transpiler niet uit de OF-config kan afleiden.
 *
 * Belangrijkste case: `userSelectGemeente`. Wanneer een ingetekend
 * polygon (of route, of adres) meerdere gemeenten raakt, wordt deze
 * Radio zichtbaar door {@see AlsReductieVan1BeginnendBij0IsGroterD}.
 * OF leverde dit veld zonder opties — wij injecteren ze hier
 * dynamisch op basis van `inGemeentenResponse.all.items` (gevuld
 * door de fetch via `ServiceFetcher::fetchInGemeentenResponse`).
 *
 * De brk_identification is de optie-key omdat downstream-rules
 * (zoals {@see AlsBoolEnIsNietGelijkAanNone580a3ef8})
 * met `gemeenten.<userSelectGemeente>` `evenementInGemeente`-zetten.
 */
final class LocatieFieldRules
{
    /**
     * @var array<string, list<string>>
     */
    public const PER_FIELD = [
        'userSelectGemeente' => [
            "->options(fn (\$livewire): array => collect((array) \$livewire->state()->get('inGemeentenResponse.all.items'))->mapWithKeys(fn (\$item) => [(string) (\$item['brk_identification'] ?? '') => (string) (\$item['name'] ?? '')])->all())",
        ],
    ];
}
