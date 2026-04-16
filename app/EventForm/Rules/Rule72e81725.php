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
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentDieren', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
