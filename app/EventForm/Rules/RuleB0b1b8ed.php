<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08
 *
 * @openforms-rule-description
 */
final class RuleB0b1b8ed implements Rule
{
    public function identifier(): string
    {
        return 'b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('evenementInGemeente') !== ''));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('content200', false);
        $s->setFieldHidden('algemeneVragen', false);
        $s->setFieldHidden('contentGemeenteMelding', false);
    }
}
