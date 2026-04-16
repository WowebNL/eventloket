<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid dcd1e4b3-7706-48df-a08f-3ad84369d580
 *
 * @openforms-rule-description
 */
final class RuleDcd1e4b3 implements Rule
{
    public function identifier(): string
    {
        return 'dcd1e4b3-7706-48df-a08f-3ad84369d580';
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
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('ehbo', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
