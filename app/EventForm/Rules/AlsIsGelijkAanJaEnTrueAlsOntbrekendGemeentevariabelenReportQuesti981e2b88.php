<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 981e2b88-49b3-4096-ae1d-07a4500e7ccc
 *
 * @openforms-rule-description Als ({{meldingvraag2}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
 */
final class AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti981e2b88 implements Rule
{
    public function identifier(): string
    {
        return '981e2b88-49b3-4096-ae1d-07a4500e7ccc';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('meldingvraag2') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_3',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
