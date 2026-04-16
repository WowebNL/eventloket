<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 335471e6-3df8-41ea-955b-dc35b69e947d
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0928EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '335471e6-3df8-41ea-955b-dc35b69e947d';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend19');
    }
}
