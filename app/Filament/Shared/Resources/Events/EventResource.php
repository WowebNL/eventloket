<?php

namespace App\Filament\Shared\Resources\Events;

use App\Models\Event;
use Filament\Resources\Resource;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $slug = 'events';

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('resources/event.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/event.plural_label');
    }
}
