<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 63e3968d-ef2b-44c8-9410-748098a86e7e
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0899')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0899EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '63e3968d-ef2b-44c8-9410-748098a86e7e';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0899') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend14');
    }
}
