<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d566bba6-452c-480c-9a12-fcee922d0002
 *
 * @openforms-rule-description
 */
final class RuleD566bba6 implements Rule
{
    public function identifier(): string
    {
        return 'd566bba6-452c-480c-9a12-fcee922d0002';
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
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', false);
    }
}
