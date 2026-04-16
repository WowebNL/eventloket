<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 935dc38c-383c-4c3d-abe1-a741bfba4a32
 *
 * @openforms-rule-description
 */
final class Rule935dc38c implements Rule
{
    public function identifier(): string
    {
        return '935dc38c-383c-4c3d-abe1-a741bfba4a32';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wCs', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
