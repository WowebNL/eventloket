<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 599a6cfd-7ea4-4c68-b011-c1f590286daf
 *
 * @openforms-rule-description Als bool({{routesOpKaart}})en ({{routesOpKaart}} is niet gelijk aan 'None')
 */
final class AlsBoolEnIsNietGelijkAanNone599a6cfd implements Rule
{
    public function identifier(): string
    {
        return '599a6cfd-7ea4-4c68-b011-c1f590286daf';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((((bool) $s->get('routesOpKaart')) && ($s->get('routesOpKaart') !== 'None')));
    }

    public function apply(FormState $s): void {}
}
