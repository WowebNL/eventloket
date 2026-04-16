<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 49389fc0-4da8-4449-acaf-674a2e2fb0e2
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0938EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '49389fc0-4da8-4449-acaf-674a2e2fb0e2';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend25');
    }
}
