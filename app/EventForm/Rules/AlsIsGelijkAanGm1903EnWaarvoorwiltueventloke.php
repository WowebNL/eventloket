<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid a46b5971-673b-415a-a7b4-fa4dde2e0c4f
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm1903EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return 'a46b5971-673b-415a-a7b4-fa4dde2e0c4f';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend17');
    }
}
