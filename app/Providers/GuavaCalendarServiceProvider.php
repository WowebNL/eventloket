<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Guava\Calendar\CalendarServiceProvider;
use ReflectionClass;

/**
 * Overrides the upstream CalendarServiceProvider to replace the hardcoded
 * CDN URLs (cdn.jsdelivr.net) with locally-served files so that the
 * Content-Security-Policy can enforce script-src / style-src 'self'.
 *
 * The guava/calendar package is excluded from auto-discovery in composer.json;
 * this provider is registered manually in bootstrap/providers.php instead.
 */
class GuavaCalendarServiceProvider extends CalendarServiceProvider
{
    protected function getPackageBaseDir(): string
    {
        // Spatie resolves views/translations relative to the service provider file.
        // Without this override, it would resolve to app/ instead of the vendor package.
        return dirname((new ReflectionClass(CalendarServiceProvider::class))->getFileName());
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            assets: [
                AlpineComponent::make('calendar', base_path('vendor/guava/calendar/dist/js/calendar.js')),
                AlpineComponent::make('calendar-context-menu', base_path('vendor/guava/calendar/dist/js/calendar-context-menu.js')),
                AlpineComponent::make('calendar-event', base_path('vendor/guava/calendar/dist/js/calendar-event.js')),
                Css::make('calendar-styles', asset('vendor/event-calendar/event-calendar.min.css')),
                Js::make('calendar-script', asset('vendor/event-calendar/event-calendar.min.js')),
            ],
            package: 'guava/calendar'
        );
    }
}
