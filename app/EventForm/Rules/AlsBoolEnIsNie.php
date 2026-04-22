<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid 583c258c-fcbd-4f1c-b127-58d04b6ed050
 *
 * @openforms-rule-description Als bool({{eventloketSession.organisation_name}})en ({{eventloketSession.organisation_name}} is nie…
 */
final class AlsBoolEnIsNie implements Rule
{
    public function identifier(): string
    {
        return '583c258c-fcbd-4f1c-b127-58d04b6ed050';
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
        return (bool) (JsTruthy::of($s->get('eventloketSession.organisation_name')) && ($s->get('eventloketSession.organisation_name') !== 'None') && ($s->get('eventloketSession.organisation_name') !== 'NULL'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('watIsDeNaamVanUwOrganisatie', $s->get('eventloketSession.organisation_name'));
    }
}
