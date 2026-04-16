<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid f56a54dd-4af9-452f-8bbd-cee5fba3c79b
 *
 * @openforms-rule-description
 */
final class RuleF56a54dd implements Rule
{
    public function identifier(): string
    {
        return 'f56a54dd-4af9-452f-8bbd-cee5fba3c79b';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('eventloketSession') !== '{}'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('watIsUwVoornaam', $s->get('eventloketSession.user_first_name'));
        $s->setVariable('watIsUwEMailadres', $s->get('eventloketSession.user_email'));
        $s->setVariable('watIsUwTelefoonnummer', $s->get('eventloketSession.user_phone'));
        $s->setVariable('watIsHetKamerVanKoophandelNummerVanUwOrganisatie', $s->get('eventloketSession.kvk'));
        $s->setFieldHidden('loadUserInformation', true);
        $s->setVariable('eventloketPrefill', (((bool) $s->get('eventloketSession.prefill_data')) ? $s->get('eventloketSession.prefill_data') : '{}'));
    }
}
