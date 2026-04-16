<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8124340f-cce5-47da-8691-91ad37fd6af0
 *
 * @openforms-rule-description Als bool({{eventloketSession.user_last_name}})en ({{eventloketSession.user_last_name}} is niet geli…
 */
final class AlsBoolEnIsNietGeli implements Rule
{
    public function identifier(): string
    {
        return '8124340f-cce5-47da-8691-91ad37fd6af0';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((((bool) $s->get('eventloketSession.user_last_name')) && ($s->get('eventloketSession.user_last_name') !== 'None') && ($s->get('eventloketSession.user_last_name') !== 'NULL')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('watIsUwAchternaam', $s->get('eventloketSession.user_last_name'));
    }
}
