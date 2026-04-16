<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e0d010cd-193d-4a26-8a01-89b185d5709e
 *
 * @openforms-rule-description
 */
final class RuleE0d010cd implements Rule
{
    public function identifier(): string
    {
        return 'e0d010cd-193d-4a26-8a01-89b185d5709e';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('andersGroup', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
