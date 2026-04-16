<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 4e724924-c5a7-451b-a2c5-282cf9a245ed
 *
 * @openforms-rule-description
 */
final class Rule4e724924 implements Rule
{
    public function identifier(): string
    {
        return '4e724924-c5a7-451b-a2c5-282cf9a245ed';
    }

    public function triggerStepUuids(): array
    {
        return ['8facfe56-5548-44e7-93b9-1356bc266e00'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('confirmationtext', '');
    }
}
