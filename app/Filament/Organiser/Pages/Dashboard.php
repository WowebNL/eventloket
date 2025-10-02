<?php

namespace App\Filament\Organiser\Pages;

use App\Filament\Organiser\Widgets\Intro;
use App\Filament\Organiser\Widgets\OrganiserThreadInboxWidget;
use App\Filament\Organiser\Widgets\Shortlink;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = 0;

    public function getTitle(): string|Htmlable
    {
        return self::greet().' '.auth()->user()->name;
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public static function greet(): string
    {
        $hour = Carbon::now()->format('H');

        return match (true) {
            $hour < 6 => __('organiser/pages/dashboard.greet.night'),
            $hour >= 6 && $hour < 12 => __('organiser/pages/dashboard.greet.morning'),
            $hour >= 12 && $hour < 18 => __('organiser/pages/dashboard.greet.afternoon'),
            default => __('organiser/pages/dashboard.greet.evening'),
        };
    }

    public function getWidgets(): array
    {
        return [
            Intro::class,
            Shortlink::class,
            OrganiserThreadInboxWidget::class,
        ];
    }
}
