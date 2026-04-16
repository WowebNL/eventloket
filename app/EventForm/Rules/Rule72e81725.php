<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 72e81725-03fc-4c6e-8218-603bc7f07ef8
 *
 * @openforms-rule-description
 */
final class Rule72e81725 implements Rule
{
    public function identifier(): string
    {
        return '72e81725-03fc-4c6e-8218-603bc7f07ef8';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentDieren', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
