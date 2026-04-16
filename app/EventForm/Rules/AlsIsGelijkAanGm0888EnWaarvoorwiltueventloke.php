<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 32426416-9787-42d5-8eb2-4634a214e0ea
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0888EnWaarvoorwiltueventloke implements Rule
{
    public function identifier(): string
    {
        return '32426416-9787-42d5-8eb2-4634a214e0ea';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend9');
    }
}
