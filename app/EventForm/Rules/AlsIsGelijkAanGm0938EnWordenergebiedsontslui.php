<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 553d3dce-5469-46d9-a804-5a168e60d7bd
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0938EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '553d3dce-5469-46d9-a804-5a168e60d7bd';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend27');
    }
}
