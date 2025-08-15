<?php

namespace App\Filament\Organiser\Pages;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getTitle(): string|Htmlable
    {
        return self::greet().' '.auth()->user()->name;
    }

    public function getColumns(): int|string|array
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
}
