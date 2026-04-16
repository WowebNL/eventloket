<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d88a64d4-9e6e-43d8-86f4-305d774ffd07
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0899')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0899En implements Rule
{
    public function identifier(): string
    {
        return 'd88a64d4-9e6e-43d8-86f4-305d774ffd07';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0899') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend15');
    }
}
