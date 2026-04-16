<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 199313af-cc35-4409-8398-294c658ae03f
 *
 * @openforms-rule-description
 */
final class Rule199313af implements Rule
{
    public function identifier(): string
    {
        return '199313af-cc35-4409-8398-294c658ae03f';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentLasershow', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
