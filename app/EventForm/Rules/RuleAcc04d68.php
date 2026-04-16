<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid acc04d68-e446-4c59-b8a5-d40ef246ee74
 *
 * @openforms-rule-description
 */
final class RuleAcc04d68 implements Rule
{
    public function identifier(): string
    {
        return 'acc04d68-e446-4c59-b8a5-d40ef246ee74';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('Speeltoestellen', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
