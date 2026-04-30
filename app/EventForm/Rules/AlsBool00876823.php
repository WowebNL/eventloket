<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Support\JsTruthy;

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

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['00f09aee-fedd-44d6-b82c-3e3754d67b7a'];
    }

    public function applies(FormState $s): bool
    {
        return JsTruthy::of($s->get('evenementenInDeGemeente'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('evenmentenInDeBuurtContent', false);
    }
}
