<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4a05099f-5ded-49b6-a0a6-fc1544b55c25
 *
 * @openforms-rule-description
 */
final class Rule4a05099f implements Rule
{
    public function identifier(): string
    {
        return '4a05099f-5ded-49b6-a0a6-fc1544b55c25';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('groteVoertuigen', false);
        $s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);
    }
}
