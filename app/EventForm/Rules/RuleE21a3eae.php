<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e21a3eae-6e0f-479e-84e7-122e3401aac4
 *
 * @openforms-rule-description
 */
final class RuleE21a3eae implements Rule
{
    public function identifier(): string
    {
        return 'e21a3eae-6e0f-479e-84e7-122e3401aac4';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('verkooppuntenMuntenEnBonnen', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
        $s->setFieldHidden('verkooppuntenCashless', false);
    }
}
