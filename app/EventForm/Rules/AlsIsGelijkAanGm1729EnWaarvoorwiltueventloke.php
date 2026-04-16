<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4fb78bad-07fb-473d-bc18-bee1bad8503f
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm1729EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '4fb78bad-07fb-473d-bc18-bee1bad8503f';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend5');
    }
}
