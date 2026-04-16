<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid f5363d0b-b344-4350-86c4-063b2ea97516
 *
 * @openforms-rule-description
 */
final class RuleF5363d0b implements Rule
{
    public function identifier(): string
    {
        return 'f5363d0b-b344-4350-86c4-063b2ea97516';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentWapen', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
