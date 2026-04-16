<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4787de8e-7323-46ae-abf8-ff3f365ab262
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0938En implements Rule
{
    public function identifier(): string
    {
        return '4787de8e-7323-46ae-abf8-ff3f365ab262';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend26');
    }
}
