<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 7b13e485-188e-4b37-8a31-c310ed165109
 *
 * @openforms-rule-description
 */
final class Rule7b13e485 implements Rule
{
    public function identifier(): string
    {
        return '7b13e485-188e-4b37-8a31-c310ed165109';
    }

    public function triggerStepUuids(): array
    {
        return ['f4e91db5-fd74-4eba-b818-96ed2cc07d84'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers1') === 'Ja'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1', false);
    }
}
