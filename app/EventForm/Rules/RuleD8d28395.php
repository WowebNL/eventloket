<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d8d28395-9e5e-4570-a4f3-129ad988ae8f
 *
 * @openforms-rule-description
 */
final class RuleD8d28395 implements Rule
{
    public function identifier(): string
    {
        return 'd8d28395-9e5e-4570-a4f3-129ad988ae8f';
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
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('bouwsels', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
