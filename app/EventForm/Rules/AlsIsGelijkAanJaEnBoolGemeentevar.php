<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 454a40c6-43c8-42cd-9d2f-6d2ace4fec53
 *
 * @openforms-rule-description Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} is gelijk aan 'Ja')en bool({{gemeenteVar…
 */
final class AlsIsGelijkAanJaEnBoolGemeentevar implements Rule
{
    public function identifier(): string
    {
        return '454a40c6-43c8-42cd-9d2f-6d2ace4fec53';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((bool) $s->get('gemeenteVariabelen.report_question_1'))));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('meldingvraag1', false);
    }
}
