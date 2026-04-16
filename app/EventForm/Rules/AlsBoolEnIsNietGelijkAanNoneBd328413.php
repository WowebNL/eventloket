<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid bd328413-a566-42a6-87ba-ec575ea94347
 *
 * @openforms-rule-description Als bool({{addressesToCheck}})en ({{addressesToCheck}} is niet gelijk aan 'None')
 */
final class AlsBoolEnIsNietGelijkAanNoneBd328413 implements Rule
{
    public function identifier(): string
    {
        return 'bd328413-a566-42a6-87ba-ec575ea94347';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((((bool) $s->get('addressesToCheck')) && ($s->get('addressesToCheck') !== 'None')));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);
    }
}
