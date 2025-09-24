<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers;

use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class OrganiserThreadsRelationManager extends RelationManager
{
    protected static string $relationship = 'organiserThreads';

    protected static ?string $relatedResource = OrganiserThreadResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->authorize('create'),
            ]);
    }
}
