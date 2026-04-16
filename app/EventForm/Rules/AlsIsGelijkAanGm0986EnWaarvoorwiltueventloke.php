<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 78ef160d-4aa3-4fe9-941c-848501f3bc60
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0986EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '78ef160d-4aa3-4fe9-941c-848501f3bc60';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend43');
    }
}
