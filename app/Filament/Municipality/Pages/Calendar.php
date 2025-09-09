<?php

namespace App\Filament\Municipality\Pages;

use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;

class Calendar extends BaseCalendar
{
    protected function getHeaderWidgets(): array
    {
        return [
            MunicipalityCalendarWidget::make(),
        ];
    }
}
