<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid a64ed84a-d0a3-4560-b782-a24be41b3e4a
 *
 * @openforms-rule-description Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} is gelijk aan 'Nee')en (true als ontbrek…
 */
final class AlsIsGelijkAanNeeEnTrueAlsOntbrek implements Rule
{
    public function identifier(): string
    {
        return 'a64ed84a-d0a3-4560-b782-a24be41b3e4a';
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
        return (bool) ((($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_1',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
