<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid 0f284f5c-ffb1-4512-981d-5954e56c8b9e
 *
 * @openforms-rule-description Als bool({{eventloketSession.organisation_phone}})en ({{eventloketSession.organisation_phone}} is n…
 */
final class AlsBoolEnIsN0f284f5c implements Rule
{
    public function identifier(): string
    {
        return '0f284f5c-ffb1-4512-981d-5954e56c8b9e';
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
        return (bool) (JsTruthy::of($s->get('eventloketSession.organisation_phone')) && ($s->get('eventloketSession.organisation_phone') !== 'None') && ($s->get('eventloketSession.organisation_phone') !== 'NULL'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('telefoonnummerOrganisatie', $s->get('eventloketSession.organisation_phone'));
    }
}
