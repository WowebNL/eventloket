<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 37d78597-b439-44be-8e85-49a9a6bdb047
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0882EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '37d78597-b439-44be-8e85-49a9a6bdb047';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend22');
    }
}
