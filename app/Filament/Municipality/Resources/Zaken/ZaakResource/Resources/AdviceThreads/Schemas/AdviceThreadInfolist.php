<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas;

use App\Filament\Shared\Infolists\Components\ThreadMessagesEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdviceThreadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informatie')
                    ->description('Informatie over de thread')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('type'),
                        TextEntry::make('advisory.name')
                            ->label('Adviesdienst'),
                        TextEntry::make('advice_status'),
                        TextEntry::make('advice_due_at')
                            ->dateTime(),
                        TextEntry::make('createdBy.name')
                            ->label('Aangemaakt door'),
                    ]),

                ThreadMessagesEntry::make('messages'),
            ]);
    }
}
