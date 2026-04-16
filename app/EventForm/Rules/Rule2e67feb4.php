<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2e67feb4-08d6-46f8-ab24-3ee91a387cb7
 *
 * @openforms-rule-description
 */
final class Rule2e67feb4 implements Rule
{
    public function identifier(): string
    {
        return '2e67feb4-08d6-46f8-ab24-3ee91a387cb7';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wegOfVaarwegAfsluiten', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
    }
}
