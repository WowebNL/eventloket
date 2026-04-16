<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6b2aeed1-8226-4a7c-9801-bbe61d576dca
 *
 * @openforms-rule-description
 */
final class Rule6b2aeed1 implements Rule
{
    public function identifier(): string
    {
        return '6b2aeed1-8226-4a7c-9801-bbe61d576dca';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentGemeenteMelding', false);
        $s->setFieldHidden('algemeneVragen', false);
    }
}
