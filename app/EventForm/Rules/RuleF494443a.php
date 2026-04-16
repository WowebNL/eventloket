<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c
 *
 * @openforms-rule-description
 */
final class RuleF494443a implements Rule
{
    public function identifier(): string
    {
        return 'f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c';
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
        return (bool) (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('vervoersmaatregelen', false);
        $s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);
    }
}
