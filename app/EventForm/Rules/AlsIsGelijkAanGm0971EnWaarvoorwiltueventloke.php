<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d442d0f7-b6d4-488a-9a4a-37e814e93769
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0971EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return 'd442d0f7-b6d4-488a-9a4a-37e814e93769';
    }

    public function triggerStepUuids(): array
    {
        return ['8facfe56-5548-44e7-93b9-1356bc266e00'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend34');
    }
}
