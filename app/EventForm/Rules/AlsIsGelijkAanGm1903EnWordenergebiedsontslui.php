<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid c214f586-8c85-4acc-b31a-955bbcbfb029
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm1903EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'c214f586-8c85-4acc-b31a-955bbcbfb029';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend16');
    }
}
