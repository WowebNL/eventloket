<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 75d3584d-6746-4946-9ad3-6672c8dd11b6
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0994')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0994EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '75d3584d-6746-4946-9ad3-6672c8dd11b6';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0994') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend42');
    }
}
