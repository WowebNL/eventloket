<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid ad8eb74d-08d5-4813-9c00-a914f6618300
 *
 * @openforms-rule-description
 */
final class RuleAd8eb74d implements Rule
{
    public function identifier(): string
    {
        return 'ad8eb74d-08d5-4813-9c00-a914f6618300';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentVuurkorf', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);
    }
}
