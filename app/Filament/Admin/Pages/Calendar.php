<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AdminCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;

class Calendar extends BaseCalendar
{
    protected function getHeaderWidgets(): array
    {
        return [
            AdminCalendarWidget::make(),
        ];
    }
}
