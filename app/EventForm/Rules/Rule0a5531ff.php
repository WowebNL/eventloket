<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 0a5531ff-5f95-42e3-b911-53affa4c88d6
 *
 * @openforms-rule-description
 */
final class Rule0a5531ff implements Rule
{
    public function identifier(): string
    {
        return '0a5531ff-5f95-42e3-b911-53affa4c88d6';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', false);
    }
}
