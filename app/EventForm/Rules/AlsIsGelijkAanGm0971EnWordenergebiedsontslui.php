<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6dcf345f-b3c5-4ec0-88af-969d124df26e
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0971EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '6dcf345f-b3c5-4ec0-88af-969d124df26e';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend36');
    }
}
