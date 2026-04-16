<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 889aed1d-d7bc-4a93-b5b6-00c01f812724
 *
 * @openforms-rule-description
 */
final class Rule889aed1d implements Rule
{
    public function identifier(): string
    {
        return '889aed1d-d7bc-4a93-b5b6-00c01f812724';
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
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('bouwsels', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
