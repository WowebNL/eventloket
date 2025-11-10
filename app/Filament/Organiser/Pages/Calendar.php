<?php

namespace App\Filament\Organiser\Pages;

use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;

class Calendar extends BaseCalendar
{
    protected static ?int $navigationSort = 4;

    protected function getHeaderWidgets(): array
    {
        return [
            OrganiserCalendarWidget::make(),
        ];
    }
}
