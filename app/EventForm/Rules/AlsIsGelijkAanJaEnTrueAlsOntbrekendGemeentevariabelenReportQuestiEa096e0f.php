<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid ea096e0f-e793-4df7-8292-df26ad862dc9
 *
 * @openforms-rule-description Als ({{meldingvraag1}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
 */
final class AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiEa096e0f implements Rule
{
    public function identifier(): string
    {
        return 'ea096e0f-e793-4df7-8292-df26ad862dc9';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('meldingvraag1') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_2',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
