<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MunicipalityFormQuestionType: string implements HasLabel
{
    case Text = 'text';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';

    public function getLabel(): string
    {
        return __("enums/municipality-form-question-type.{$this->value}.label");
    }

    /**
     * Whether this type needs a list of options to be answerable.
     */
    public function needsOptions(): bool
    {
        return $this === self::Radio || $this === self::Checkboxes;
    }
}
