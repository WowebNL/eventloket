<?php

namespace App\Filament\Shared\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Calendar extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public function getTitle(): string|Htmlable
    {
        return __('shared/widgets/calendar.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared/widgets/calendar.title');
    }
}
