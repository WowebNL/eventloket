<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6a6642d7-c35c-4bd8-b32e-5e05ac85da71
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0994')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0994En implements Rule
{
    public function identifier(): string
    {
        return '6a6642d7-c35c-4bd8-b32e-5e05ac85da71';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0994') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend41');
    }
}
