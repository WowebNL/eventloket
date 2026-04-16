<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8aa421de-5ac8-4451-a646-ef94e82e0d00
 *
 * @openforms-rule-description
 */
final class Rule8aa421de implements Rule
{
    public function identifier(): string
    {
        return '8aa421de-5ac8-4451-a646-ef94e82e0d00';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('tenten', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
