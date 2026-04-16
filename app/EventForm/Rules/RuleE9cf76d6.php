<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e9cf76d6-9eca-4d23-b546-f6f4a9c4d471
 *
 * @openforms-rule-description
 */
final class RuleE9cf76d6 implements Rule
{
    public function identifier(): string
    {
        return 'e9cf76d6-9eca-4d23-b546-f6f4a9c4d471';
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
        return (bool) (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('voorwerpen', false);
        $s->setFieldHidden('Lichtmasten', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);
    }
}
