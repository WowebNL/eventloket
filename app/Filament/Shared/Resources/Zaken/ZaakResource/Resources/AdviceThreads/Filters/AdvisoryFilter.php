<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters;

use Filament\Tables\Filters\SelectFilter;

class AdvisoryFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('advisory_id')
            ->label(__('resources/advice_thread.columns.advisory.label'))
            ->relationship('advisory', 'name')
            ->multiple();
    }
}
