<?php

namespace App\Filament\Advisor\Resources\Zaken;

use App\Filament\Advisor\Resources\Zaken\ZaakResource\Pages\ListAllZaken;
use App\Filament\Shared\Resources\Zaken\ZaakResource as BaseZaakResource;
use App\Models\Advisory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ZaakResource extends BaseZaakResource
{
    protected static bool $isDiscovered = true;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @param  Advisory|null  $tenant
     */
    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        if ($tenant->can_view_any_zaak) {
            return $query;
        }

        return $query->whereHas('adviceThreads', fn (Builder $query) => $query->where('advisory_id', $tenant->id));
    }

    public static function getPages(): array
    {
        return [
            'all' => ListAllZaken::route('/all'),
            ...parent::getPages(),
        ];
    }
}
