<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters;

use App\Enums\AdviceStatus;
use Filament\Tables\Filters\SelectFilter;

class AdviceStatusFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('advice_status')
            ->label(__('resources/advice_thread.columns.advice_status.label'))
            ->options(AdviceStatus::class);
    }
}
