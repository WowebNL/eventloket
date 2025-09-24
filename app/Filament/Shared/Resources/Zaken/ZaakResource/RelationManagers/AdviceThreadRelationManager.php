<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers;

use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class AdviceThreadRelationManager extends RelationManager
{
    protected static string $relationship = 'adviceThreads';

    protected static ?string $relatedResource = AdviceThreadResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->authorize(true),
            ]);
    }
}
