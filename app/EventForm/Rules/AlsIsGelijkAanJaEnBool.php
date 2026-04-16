<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 63781392-9b7b-45e3-823d-5b039784882e
 *
 * @openforms-rule-description Als ({{meldingvraag4}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_5}})
 */
final class AlsIsGelijkAanJaEnBool implements Rule
{
    public function identifier(): string
    {
        return '63781392-9b7b-45e3-823d-5b039784882e';
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
        return (bool) ((($s->get('meldingvraag4') === 'Ja') && ((bool) $s->get('gemeenteVariabelen.report_question_5'))));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('meldingvraag5', false);
    }
}
