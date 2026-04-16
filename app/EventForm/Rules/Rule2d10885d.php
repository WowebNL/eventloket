<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2d10885d-3e3a-4df1-a17b-d979668d2581
 *
 * @openforms-rule-description
 */
final class Rule2d10885d implements Rule
{
    public function identifier(): string
    {
        return '2d10885d-3e3a-4df1-a17b-d979668d2581';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('brandstofopslag', false);
        $s->setFieldHidden('brandgevaarlijkeStoffen', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
