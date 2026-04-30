<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Support\JsTruthy;

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

    public function triggerStepUuids(): array
    {
        return ['2186344f-9821-45d1-bd52-9900ae15fcb6'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (JsTruthy::of($s->get('routesOpKaart')) && ($s->get('routesOpKaart') !== 'None'));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);
    }
}
