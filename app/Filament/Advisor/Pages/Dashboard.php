<?php

namespace App\Filament\Advisor\Pages;

use App\Filament\Advisor\Widgets\AdviceThreadInboxWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            AdviceThreadInboxWidget::class,
        ];
    }
}
