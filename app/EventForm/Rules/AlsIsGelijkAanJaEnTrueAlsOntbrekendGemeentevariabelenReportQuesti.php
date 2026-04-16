<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid ceac4877-e22f-4d59-afac-cf2f29cb93d9
 *
 * @openforms-rule-description Als ({{meldingvraag4}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
 */
final class AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti implements Rule
{
    public function identifier(): string
    {
        return 'ceac4877-e22f-4d59-afac-cf2f29cb93d9';
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
        return (bool) (($s->get('meldingvraag4') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_5',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
