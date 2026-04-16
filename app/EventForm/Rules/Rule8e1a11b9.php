<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8e1a11b9-59f2-407b-8fb1-0fbee9712c08
 *
 * @openforms-rule-description
 */
final class Rule8e1a11b9 implements Rule
{
    public function identifier(): string
    {
        return '8e1a11b9-59f2-407b-8fb1-0fbee9712c08';
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
        return (bool) ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('bouwsels10MSup2Sup', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
        $s->setFieldHidden('watVoorBouwselsPlaatsUOpDeLocaties', false);
    }
}
