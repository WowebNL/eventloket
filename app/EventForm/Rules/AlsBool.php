<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Support\JsTruthy;

/**
 * @openforms-rule-uuid ce043762-6d77-44dc-8e8c-cb605e9acdfa
 *
 * @openforms-rule-description Als bool({{eventloketSession.kvk}})
 */
final class AlsBool implements Rule
{
    public function identifier(): string
    {
        return 'ce043762-6d77-44dc-8e8c-cb605e9acdfa';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5'];
    }

    public function applies(FormState $s): bool
    {
        return JsTruthy::of($s->get('eventloketSession.kvk'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('organisatieInformatie', false);
        $s->setFieldHidden('adresgegevens', true);
    }
}
