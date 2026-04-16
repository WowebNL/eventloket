<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 9ac0b4c7-ea17-48c4-9bd0-b760ed0570ba
 *
 * @openforms-rule-description
 */
final class Rule9ac0b4c7 implements Rule
{
    public function identifier(): string
    {
        return '9ac0b4c7-ea17-48c4-9bd0-b760ed0570ba';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('binnenVeiligheidsregio') === false));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('NotWithin', false);
    }
}
