<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 00876823-b3f3-44f6-a177-d355c84c0b12
 *
 * @openforms-rule-description Als bool({{evenementenInDeGemeente}})
 */
final class AlsBool00876823 implements Rule
{
    public function identifier(): string
    {
        return '00876823-b3f3-44f6-a177-d355c84c0b12';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (((bool) $s->get('evenementenInDeGemeente')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('evenmentenInDeBuurtContent', false);
    }
}
