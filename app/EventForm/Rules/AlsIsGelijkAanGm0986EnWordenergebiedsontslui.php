<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid e0974420-8ac8-4c94-9f69-6b5c1f326d33
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0986EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return 'e0974420-8ac8-4c94-9f69-6b5c1f326d33';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend45');
    }
}
