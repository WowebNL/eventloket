<?php

namespace App\Filament\Advisor\Resources\Zaken\ZaakResource\Pages;

use App\Filament\Advisor\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Zaken\Tables\ZakenTable;
use App\Models\Advisory;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListAllZaken extends ListRecords
{
    protected static string $resource = ZaakResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        /** @var Advisory $tenant */
        $tenant = Filament::getTenant();

        return $tenant->can_view_any_zaak;
    }

    public function table(Table $table): Table
    {
        $table = ZakenTable::configure($table);

        $filters = $table->getFilters();

        unset($filters['workingstock-advisor']);

        $table->filters($filters);

        return $table;
    }
}
