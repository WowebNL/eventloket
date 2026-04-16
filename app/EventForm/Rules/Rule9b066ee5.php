<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 9b066ee5-3e95-45a1-9864-c444f1508300
 *
 * @openforms-rule-description
 */
final class Rule9b066ee5 implements Rule
{
    public function identifier(): string
    {
        return '9b066ee5-3e95-45a1-9864-c444f1508300';
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
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('kansspelen', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
