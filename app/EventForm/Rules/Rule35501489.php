<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 35501489-2e07-4d62-b5df-da1b4795d5e7
 *
 * @openforms-rule-description
 */
final class Rule35501489 implements Rule
{
    public function identifier(): string
    {
        return '35501489-2e07-4d62-b5df-da1b4795d5e7';
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
        return (bool) ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentBalon', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
