<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid b4fefcd8-faae-4139-93e1-e4b8108d6376
 *
 * @openforms-rule-description
 */
final class RuleB4fefcd8 implements Rule
{
    public function identifier(): string
    {
        return 'b4fefcd8-faae-4139-93e1-e4b8108d6376';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['c75cc256-6729-4684-9f9b-ede6265b3e72'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ($s->get('risicoClassificatie') !== '');
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('risicoClassificatieContent', false);
    }
}
