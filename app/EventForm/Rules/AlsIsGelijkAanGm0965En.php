<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 789875f2-c16c-4136-ab2c-02a990496a67
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0965En implements Rule
{
    public function identifier(): string
    {
        return '789875f2-c16c-4136-ab2c-02a990496a67';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('isVergunningaanvraag') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend29');
    }
}
