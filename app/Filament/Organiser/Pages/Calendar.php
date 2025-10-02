<?php

namespace App\Filament\Organiser\Pages;

use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;
use App\Filament\Shared\Resources\Events\EventResource;

class Calendar extends BaseCalendar
{
    protected static ?int $navigationSort = 4;

    public static string $resource = EventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            OrganiserCalendarWidget::make(),
        ];
    }
}
