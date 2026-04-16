<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d315e853-7945-434c-8124-99fdf289a207
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0882EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'd315e853-7945-434c-8124-99fdf289a207';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend24');
    }
}
