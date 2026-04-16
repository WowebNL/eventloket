<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 615d524a-498d-4e30-8279-2dc41ec7d6ac
 *
 * @openforms-rule-description
 */
final class Rule615d524a implements Rule
{
    public function identifier(): string
    {
        return '615d524a-498d-4e30-8279-2dc41ec7d6ac';
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
        return (bool) ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('geluidstorens', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
