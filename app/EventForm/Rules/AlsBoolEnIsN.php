<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 5905fff0-6bec-4c28-9064-55772fb25859
 *
 * @openforms-rule-description Als bool({{eventloketSession.organisation_email}})en ({{eventloketSession.organisation_email}} is n…
 */
final class AlsBoolEnIsN implements Rule
{
    public function identifier(): string
    {
        return '5905fff0-6bec-4c28-9064-55772fb25859';
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
        return (bool) ((bool) $s->get('eventloketSession.organisation_email') && ($s->get('eventloketSession.organisation_email') !== 'None') && ($s->get('eventloketSession.organisation_address') !== 'NULL'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('emailadresOrganisatie', $s->get('eventloketSession.organisation_email'));
    }
}
