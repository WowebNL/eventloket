<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AdminCalendarWidget;
use App\Filament\Shared\Imports\ZaakImporter;
use App\Filament\Shared\Pages\Calendar as BaseCalendar;
use Filament\Actions\ImportAction;

class Calendar extends BaseCalendar
{
    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label(__('shared/widgets/calendar.actions.import.label'))
                ->modalHeading(__('shared/widgets/calendar.actions.import.label'))
                ->importer(ZaakImporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AdminCalendarWidget::make(),
        ];
    }
}
