<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

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

    public function applies(FormState $s): bool
    {
        return (bool) (((bool) $s->get('eventloketSession.kvk')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('organisatieInformatie', false);
        $s->setFieldHidden('adresgegevens', true);
    }
}
