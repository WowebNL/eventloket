<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 32f9bd89-ac3d-4fa4-b89f-1b9a48b13efb
 *
 * @openforms-rule-description
 */
final class Rule32f9bd89 implements Rule
{
    public function identifier(): string
    {
        return '32f9bd89-ac3d-4fa4-b89f-1b9a48b13efb';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers') === 'Ja'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1', false);
    }
}
