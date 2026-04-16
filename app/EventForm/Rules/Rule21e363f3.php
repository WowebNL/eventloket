<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 21e363f3-9ca8-42d4-b52e-bddfab43ddd6
 *
 * @openforms-rule-description
 */
final class Rule21e363f3 implements Rule
{
    public function identifier(): string
    {
        return '21e363f3-9ca8-42d4-b52e-bddfab43ddd6';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc', false);
        $s->setFieldHidden('bouwsels', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
