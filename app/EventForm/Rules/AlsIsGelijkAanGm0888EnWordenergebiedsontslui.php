<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid fdbb12fb-57d0-40a6-b262-6e06dc6f903c
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0888EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'fdbb12fb-57d0-40a6-b262-6e06dc6f903c';
    }

    public function triggerStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend8');
    }
}
