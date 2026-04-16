<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e0746436-6115-4ad9-9c76-aa7adcaba646
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm1729EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'e0746436-6115-4ad9-9c76-aa7adcaba646';
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
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend7');
    }
}
