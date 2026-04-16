<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid cf1c0126-2fcf-4944-a72b-d9b2eab070cf
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm1903En implements Rule
{
    public function identifier(): string
    {
        return 'cf1c0126-2fcf-4944-a72b-d9b2eab070cf';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('isVergunningaanvraag') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend18');
    }
}
