<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d5681327-869c-4a3a-be73-88c973668af1
 *
 * @openforms-rule-description
 */
final class RuleD5681327 implements Rule
{
    public function identifier(): string
    {
        return 'd5681327-869c-4a3a-be73-88c973668af1';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('beveiligers1', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
