<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 58f8be55-1cee-404b-b5f2-db14c22127ab
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0971En implements Rule
{
    public function identifier(): string
    {
        return '58f8be55-1cee-404b-b5f2-db14c22127ab';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend35');
    }
}
