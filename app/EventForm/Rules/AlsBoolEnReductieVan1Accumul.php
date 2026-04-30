<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Support\JsTruthy;

/**
 * @openforms-rule-uuid 3247522b-8603-4c7c-ae8d-b92a75fb35d6
 *
 * @openforms-rule-description Als bool({{routeDoorGemeentenNamen}})en ((reductie van {{evenementInGemeentenNamen}} (1 + {{accumul…
 */
final class AlsBoolEnReductieVan1Accumul implements Rule
{
    public function identifier(): string
    {
        return '3247522b-8603-4c7c-ae8d-b92a75fb35d6';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['2186344f-9821-45d1-bd52-9900ae15fcb6'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (JsTruthy::of($s->get('routeDoorGemeentenNamen')) && ((is_array($s->get('routeDoorGemeentenNamen')) ? count($s->get('routeDoorGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo', false);
    }
}
