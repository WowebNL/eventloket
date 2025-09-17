<?php

namespace App\Filament\Advisor\Resources\Zaken;

use App\Filament\Shared\Resources\Zaken\ZaakResource as BaseZaakResource;
use App\Models\Advisory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ZaakResource extends BaseZaakResource
{
    /**
     * @param  Advisory|null  $tenant
     */
    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('adviceThreads', fn (Builder $query) => $query->where('advisory_id', $tenant->id));
    }
}
