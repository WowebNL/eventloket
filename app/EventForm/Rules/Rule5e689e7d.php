<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 5e689e7d-0a06-4301-ada5-d36132b285cb
 *
 * @openforms-rule-description
 */
final class Rule5e689e7d implements Rule
{
    public function identifier(): string
    {
        return '5e689e7d-0a06-4301-ada5-d36132b285cb';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('waarVindtHetEvenementPlaats.gebouw') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('adresVanDeGebouwEn', false);
    }
}
