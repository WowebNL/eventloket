<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 7b285070-2c40-4d8f-9b18-d20dd745bbd4
 *
 * @openforms-rule-description
 */
final class Rule7b285070 implements Rule
{
    public function identifier(): string
    {
        return '7b285070-2c40-4d8f-9b18-d20dd745bbd4';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('versterkteMuziek', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);
        $s->setFieldHidden('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning', false);
        $s->setFieldHidden('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX', false);
    }
}
