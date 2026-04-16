<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e3992429-730a-4ed9-af3c-62ad897933fe
 *
 * @openforms-rule-description Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accumulator}}, beginnend bij 0)) is groter d…
 */
final class AlsReductieVan1BeginnendBij0IsGroterD implements Rule
{
    public function identifier(): string
    {
        return 'e3992429-730a-4ed9-af3c-62ad897933fe';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('userSelectGemeente', false);
    }
}
