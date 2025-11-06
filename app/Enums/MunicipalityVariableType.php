<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MunicipalityVariableType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case DateRange = 'date_range';
    case TimeRange = 'time_range';
    case DateTimeRange = 'date_time_range';
    case Boolean = 'boolean';
    case ReportQuestion = 'report_question';

    public function getLabel(): string
    {
        return __("enums/municipality-variable-type.{$this->value}.label");
    }
}
