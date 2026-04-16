<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8893efa1-663a-4ad6-9184-46ae7cb2ebf7
 *
 * @openforms-rule-description
 */
final class Rule8893efa1 implements Rule
{
    public function identifier(): string
    {
        return '8893efa1-663a-4ad6-9184-46ae7cb2ebf7';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('belemmeringVanVerkeer', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
