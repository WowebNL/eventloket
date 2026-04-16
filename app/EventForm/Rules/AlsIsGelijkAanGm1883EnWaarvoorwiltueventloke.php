<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 1e756a8a-4a68-4bd0-bfc0-59f2283bffde
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm1883EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '1e756a8a-4a68-4bd0-bfc0-59f2283bffde';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend31');
    }
}
