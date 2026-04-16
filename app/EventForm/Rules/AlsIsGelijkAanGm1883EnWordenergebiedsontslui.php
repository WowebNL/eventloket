<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e86be725-f23e-42b7-b3a4-98683c59d03d
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm1883EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'e86be725-f23e-42b7-b3a4-98683c59d03d';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend33');
    }
}
