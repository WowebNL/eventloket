<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid c7431a0c-f315-4768-8372-8703629228b8
 *
 * @openforms-rule-description Als ({{meldingvraag3}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_4}})
 */
final class AlsIsGelijkAanJaEnBoolC7431a0c implements Rule
{
    public function identifier(): string
    {
        return 'c7431a0c-f315-4768-8372-8703629228b8';
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
        return (bool) (($s->get('meldingvraag3') === 'Ja') && (bool) $s->get('gemeenteVariabelen.report_question_4'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('meldingvraag4', false);
    }
}
