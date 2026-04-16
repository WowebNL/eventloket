<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d138e53e-eb22-4c93-9ec5-daba437208c3
 *
 * @openforms-rule-description
 */
final class RuleD138e53e implements Rule
{
    public function identifier(): string
    {
        return 'd138e53e-eb22-4c93-9ec5-daba437208c3';
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
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentZeppelin', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
