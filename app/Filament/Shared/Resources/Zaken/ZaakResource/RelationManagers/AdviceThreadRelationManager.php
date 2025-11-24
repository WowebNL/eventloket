<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers;

use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdviceThreadRelationManager extends RelationManager
{
    protected static string $relationship = 'adviceThreads';

    protected static ?string $relatedResource = AdviceThreadResource::class;

    // public function filterTableQuery(Builder $query): Builder
    // {
    //     $query = parent::filterTableQuery($query);

    //     if (Filament::getCurrentPanel()->getId() === 'advisor') {
    //         /** @var \App\Models\Advisory $tenant */
    //         $tenant = Filament::getTenant();

    //         return $query->where('advisory_id', $tenant->id);
    //     }

    //     return $query;
    // }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->authorize('create'),
            ]);
    }
}
