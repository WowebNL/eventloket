<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e8e0f322-bd43-4e79-9a3b-be489189920b
 *
 * @openforms-rule-description
 */
final class RuleE8e0f322 implements Rule
{
    public function identifier(): string
    {
        return 'e8e0f322-bd43-4e79-9a3b-be489189920b';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('etenBereidenOfVerkopen', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
        $s->setFieldHidden('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX', false);
    }
}
