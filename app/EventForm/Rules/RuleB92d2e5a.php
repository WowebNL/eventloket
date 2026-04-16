<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7
 *
 * @openforms-rule-description
 */
final class RuleB92d2e5a implements Rule
{
    public function identifier(): string
    {
        return 'b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['661aabb7-e927-4a75-8d95-0a665c5d83fe'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('alcoholischeDranken', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
        $s->setVariable('alcoholvergunning', 'Ja');
    }
}
