<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4e042329-a992-45ae-998b-521ea980c55a
 *
 * @openforms-rule-description Als ({{meldingvraag2}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_3}})
 */
final class AlsIsGelijkAanJaEnBool4e042329 implements Rule
{
    public function identifier(): string
    {
        return '4e042329-a992-45ae-998b-521ea980c55a';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('meldingvraag2') === 'Ja') && ((bool) $s->get('gemeenteVariabelen.report_question_3'))));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('meldingvraag3', false);
    }
}
