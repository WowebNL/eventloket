<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 5bbbf229-62eb-4e9a-89fc-b67ab1610385
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0986En implements Rule
{
    public function identifier(): string
    {
        return '5bbbf229-62eb-4e9a-89fc-b67ab1610385';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('isVergunningaanvraag') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend44');
    }
}
