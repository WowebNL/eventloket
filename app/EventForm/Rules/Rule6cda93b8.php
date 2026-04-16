<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6cda93b8-4b85-4e9b-bc0e-89c45329ddac
 *
 * @openforms-rule-description
 */
final class Rule6cda93b8 implements Rule
{
    public function identifier(): string
    {
        return '6cda93b8-4b85-4e9b-bc0e-89c45329ddac';
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
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('marktkramen', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
