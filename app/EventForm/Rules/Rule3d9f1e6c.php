<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 3d9f1e6c-85a9-449d-91c5-ebef408dd538
 *
 * @openforms-rule-description
 */
final class Rule3d9f1e6c implements Rule
{
    public function identifier(): string
    {
        return '3d9f1e6c-85a9-449d-91c5-ebef408dd538';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('douches', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
