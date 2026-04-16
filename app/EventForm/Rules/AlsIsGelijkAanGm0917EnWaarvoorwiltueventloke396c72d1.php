<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 396c72d1-d354-4508-b370-5096131b4f1c
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0917')en ({{waarvoorWiltUEventloke…
 */
final class AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke396c72d1 implements Rule
{
    public function identifier(): string
    {
        return '396c72d1-d354-4508-b370-5096131b4f1c';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0917') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend1');
    }
}
