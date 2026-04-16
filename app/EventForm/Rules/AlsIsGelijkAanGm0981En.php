<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 32ef4927-9551-46b6-9eee-a8f0650c97b9
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0981')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0981En implements Rule
{
    public function identifier(): string
    {
        return '32ef4927-9551-46b6-9eee-a8f0650c97b9';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0981') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend38');
    }
}
