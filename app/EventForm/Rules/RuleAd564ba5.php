<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid ad564ba5-b144-438a-9449-dda1800ecbd3
 *
 * @openforms-rule-description
 */
final class RuleAd564ba5 implements Rule
{
    public function identifier(): string
    {
        return 'ad564ba5-b144-438a-9449-dda1800ecbd3';
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
        return (bool) ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentVuurwerk', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
