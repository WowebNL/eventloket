<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid b741d925-75bf-4b8f-a0aa-47cdb0e5341d
 *
 * @openforms-rule-description Als ({{meldingvraag3}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
 */
final class AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiB741d925 implements Rule
{
    public function identifier(): string
    {
        return 'b741d925-75bf-4b8f-a0aa-47cdb0e5341d';
    }

    public function triggerStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function effectStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('meldingvraag3') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_4',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
