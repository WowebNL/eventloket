<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 669dd594-c81b-41d7-8c12-fcc7234588c0
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm1954EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '669dd594-c81b-41d7-8c12-fcc7234588c0';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend12');
    }
}
