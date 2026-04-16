<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 145ceec2-91c7-4e67-8195-2444d734ddfc
 *
 * @openforms-rule-description
 */
final class Rule145ceec2 implements Rule
{
    public function identifier(): string
    {
        return '145ceec2-91c7-4e67-8195-2444d734ddfc';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['f4e91db5-fd74-4eba-b818-96ed2cc07d84'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('bouwsels', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
