<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b
 *
 * @openforms-rule-description
 */
final class RuleBf2ee2f8 implements Rule
{
    public function identifier(): string
    {
        return 'bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b';
    }

    public function triggerStepUuids(): array
    {
        return ['661aabb7-e927-4a75-8d95-0a665c5d83fe'];
    }

    public function effectStepUuids(): array
    {
        return ['661aabb7-e927-4a75-8d95-0a665c5d83fe'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('overkappingen', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
