<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid c737ca21-e621-449a-97e1-0c45d5cbbffe
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm1883En implements Rule
{
    public function identifier(): string
    {
        return 'c737ca21-e621-449a-97e1-0c45d5cbbffe';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend32');
    }
}
