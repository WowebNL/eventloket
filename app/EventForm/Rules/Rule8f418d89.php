<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8f418d89-637a-45a6-8092-c2242201a009
 *
 * @openforms-rule-description
 */
final class Rule8f418d89 implements Rule
{
    public function identifier(): string
    {
        return '8f418d89-637a-45a6-8092-c2242201a009';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'));
    }

    public function apply(FormState $s): void
    {
        $s->setStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9', false);
        $s->setStepApplicable('c75cc256-6729-4684-9f9b-ede6265b3e72', false);
        $s->setStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47', false);
        $s->setStepApplicable('d87c01ce-8387-43b0-a8c8-e6cf5abb6da1', false);
        $s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', false);
        $s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', false);
        $s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', false);
        $s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', false);
        $s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', false);
    }
}
