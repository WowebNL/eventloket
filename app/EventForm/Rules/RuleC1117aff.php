<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid c1117aff-045d-4bf9-80c3-0ad446282328
 *
 * @openforms-rule-description
 */
final class RuleC1117aff implements Rule
{
    public function identifier(): string
    {
        return 'c1117aff-045d-4bf9-80c3-0ad446282328';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['d790edb5-712a-4f83-87a8-1a86e4831455'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('verkooppuntenToegangsKaarten', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
