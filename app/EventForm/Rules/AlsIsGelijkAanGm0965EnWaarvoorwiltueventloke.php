<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 1ee86630-18dc-48dc-aef6-eb1756a94647
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0965EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '1ee86630-18dc-48dc-aef6-eb1756a94647';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend28');
    }
}
