<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4d1f5398-9485-4a7d-8aac-66b3ad453184
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0882En implements Rule
{
    public function identifier(): string
    {
        return '4d1f5398-9485-4a7d-8aac-66b3ad453184';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend23');
    }
}
