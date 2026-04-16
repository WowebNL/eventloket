<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 945f1606-e086-4999-983b-8b9c83dab421
 *
 * @openforms-rule-description
 */
final class Rule945f1606 implements Rule
{
    public function identifier(): string
    {
        return '945f1606-e086-4999-983b-8b9c83dab421';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['6e285ace-f891-4324-b54e-639c1cfff9fa'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentTattoo', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
