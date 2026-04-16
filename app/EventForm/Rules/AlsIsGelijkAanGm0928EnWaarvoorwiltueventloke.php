<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 32203ae3-1b0d-4293-85e3-69ec4fdbc712
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0928EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '32203ae3-1b0d-4293-85e3-69ec4fdbc712';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend20');
    }
}
