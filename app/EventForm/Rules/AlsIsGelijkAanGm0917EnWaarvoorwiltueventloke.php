<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 0e056f5a-9303-4322-9a75-300187ab62c7
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0917')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '0e056f5a-9303-4322-9a75-300187ab62c7';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0917') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend4');
    }
}
