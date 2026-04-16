<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6142841d-ea97-4e22-8ffa-90c0b9b18cdb
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0888En implements Rule
{
    public function identifier(): string
    {
        return '6142841d-ea97-4e22-8ffa-90c0b9b18cdb';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('isVergunningaanvraag') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend3');
    }
}
