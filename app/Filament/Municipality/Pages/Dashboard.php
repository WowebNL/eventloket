<?php

namespace App\Filament\Municipality\Pages;

use App\Filament\Municipality\Widgets\ThreadInboxWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            ThreadInboxWidget::class,
        ];
    }
}
