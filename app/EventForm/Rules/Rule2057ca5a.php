<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2057ca5a-9750-474e-961a-ebb7aff07f57
 *
 * @openforms-rule-description
 */
final class Rule2057ca5a implements Rule
{
    public function identifier(): string
    {
        return '2057ca5a-9750-474e-961a-ebb7aff07f57';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('submission_id') !== ''));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('eventloketSession', $s);
    }
}
