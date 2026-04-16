<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2bbecc17-8f88-474d-9399-acb4cd509541
 *
 * @openforms-rule-description
 */
final class Rule2bbecc17 implements Rule
{
    public function identifier(): string
    {
        return '2bbecc17-8f88-474d-9399-acb4cd509541';
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
        return (bool) (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('verkeersregelaars', false);
        $s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);
    }
}
