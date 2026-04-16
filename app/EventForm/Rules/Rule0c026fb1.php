<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 0c026fb1-e43c-4fa7-a33f-615efd68d3bb
 *
 * @openforms-rule-description
 */
final class Rule0c026fb1 implements Rule
{
    public function identifier(): string
    {
        return '0c026fb1-e43c-4fa7-a33f-615efd68d3bb';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('podia', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
