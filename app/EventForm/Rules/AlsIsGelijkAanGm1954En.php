<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 91870e4d-e065-462b-8c3d-686409084cf8
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm1954En implements Rule
{
    public function identifier(): string
    {
        return '91870e4d-e065-462b-8c3d-686409084cf8';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('isVergunningaanvraag') === true)));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend10');
    }
}
