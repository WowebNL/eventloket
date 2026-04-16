<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid b782fae6-2270-4f90-930a-af073989e0f9
 *
 * @openforms-rule-description
 */
final class RuleB782fae6 implements Rule
{
    public function identifier(): string
    {
        return 'b782fae6-2270-4f90-930a-af073989e0f9';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('overnachtingen', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
