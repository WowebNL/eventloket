<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf
 *
 * @openforms-rule-description
 */
final class Rule2a01382c implements Rule
{
    public function identifier(): string
    {
        return '2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf';
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
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('toegangVoorHulpdienstenIsBeperkt', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
