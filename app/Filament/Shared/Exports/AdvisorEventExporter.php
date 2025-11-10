<?php

namespace App\Filament\Shared\Exports;

use App\Models\Event;

class AdvisorEventExporter extends ExtendedEventExporter
{
    protected static ?string $model = Event::class;
}
