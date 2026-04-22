<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid 172fe1ad-207f-429a-ace2-d2d07b4ea92a
 *
 * @openforms-rule-description Als ({{meldingvraag1}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_2}})
 */
final class AlsIsGelijkAanJaEnBool172fe1ad implements Rule
{
    public function identifier(): string
    {
        return '172fe1ad-207f-429a-ace2-d2d07b4ea92a';
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
        return (bool) (($s->get('meldingvraag1') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_2')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('meldingvraag2', false);
    }
}
