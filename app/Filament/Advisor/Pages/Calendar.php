<?php

namespace App\Filament\Advisor\Pages;

use App\Filament\Advisor\Widgets\AdvisorCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;

class Calendar extends BaseCalendar
{
    protected function getHeaderWidgets(): array
    {
        return [
            AdvisorCalendarWidget::make(),
        ];
    }
}
