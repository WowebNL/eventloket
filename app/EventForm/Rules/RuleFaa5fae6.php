<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid faa5fae6-c19f-4a8b-b138-a7b98fa44b95
 *
 * @openforms-rule-description
 */
final class RuleFaa5fae6 implements Rule
{
    public function identifier(): string
    {
        return 'faa5fae6-c19f-4a8b-b138-a7b98fa44b95';
    }

    public function triggerStepUuids(): array
    {
        return ['2186344f-9821-45d1-bd52-9900ae15fcb6'];
    }

    public function effectStepUuids(): array
    {
        return ['2186344f-9821-45d1-bd52-9900ae15fcb6'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('waarVindtHetEvenementPlaats.buiten') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('locatieSOpKaart', false);
    }
}
