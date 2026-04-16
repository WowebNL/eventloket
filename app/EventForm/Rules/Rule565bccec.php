<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 565bccec-1a7b-40f3-975f-0edf8402b461
 *
 * @openforms-rule-description
 */
final class Rule565bccec implements Rule
{
    public function identifier(): string
    {
        return '565bccec-1a7b-40f3-975f-0edf8402b461';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['e8f00982-ee47-4bec-bf31-a5c8d1b05e5e'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('groteVoertuigen', false);
        $s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);
    }
}
