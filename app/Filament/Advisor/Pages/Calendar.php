<?php

namespace App\Filament\Advisor\Pages;

use App\Filament\Admin\Resources\AdminUserResource;
use App\Filament\Advisor\Widgets\AdvisorCalendarWidget;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;

class Calendar extends BaseCalendar
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AdvisorCalendarWidget::make(),
        ];
    }
}
