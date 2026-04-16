<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 759dab8e-8717-4920-b027-79d1ca081ccf
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0965EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '759dab8e-8717-4920-b027-79d1ca081ccf';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend30');
    }
}
