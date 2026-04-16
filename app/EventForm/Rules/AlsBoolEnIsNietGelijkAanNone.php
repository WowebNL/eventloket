<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid a7211d0c-f8aa-479b-b9b9-8474dbe70b75
 *
 * @openforms-rule-description Als bool({{locatieSOpKaart}})en ({{locatieSOpKaart}} is niet gelijk aan 'None')
 */
final class AlsBoolEnIsNietGelijkAanNone implements Rule
{
    public function identifier(): string
    {
        return 'a7211d0c-f8aa-479b-b9b9-8474dbe70b75';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((((bool) $s->get('locatieSOpKaart')) && ($s->get('locatieSOpKaart') !== 'None')));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);
    }
}
