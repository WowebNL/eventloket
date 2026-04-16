<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6c661796-23ba-44ad-8ad0-1bcf4cabe17d
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{isVergunningaanvraag}}…
 */
final class AlsIsGelijkAanGm1729En implements Rule
{
    public function identifier(): string
    {
        return '6c661796-23ba-44ad-8ad0-1bcf4cabe17d';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('isVergunningaanvraag') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend2');
    }
}
