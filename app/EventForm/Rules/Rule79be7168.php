<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 79be7168-edd7-48db-af66-525fa6a5815a
 *
 * @openforms-rule-description
 */
final class Rule79be7168 implements Rule
{
    public function identifier(): string
    {
        return '79be7168-edd7-48db-af66-525fa6a5815a';
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
        return (bool) ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('verzorgingVanKinderenJongerDan12Jaar', false);
        $s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);
    }
}
