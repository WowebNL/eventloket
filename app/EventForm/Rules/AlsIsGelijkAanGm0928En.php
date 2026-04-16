<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid df33eaaf-ae05-4e09-902b-a572603a746c
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm0928En implements Rule
{
    public function identifier(): string
    {
        return 'df33eaaf-ae05-4e09-902b-a572603a746c';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend21');
    }
}
