<?php

namespace App\Filament\Municipality\Pages;

use App\Filament\Shared\Widgets\AdviceThreadInboxWidget;
use App\Filament\Shared\Widgets\OrganiserThreadInboxWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            AdviceThreadInboxWidget::class,
            OrganiserThreadInboxWidget::class,
        ];
    }
}
