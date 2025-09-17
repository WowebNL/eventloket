<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas;

use App\Filament\Shared\Infolists\Components\ThreadMessagesEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class AdviceThreadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('advice_status')
                            ->label(__('resources/advice_thread.columns.advice_status.label'))
                            ->badge()
                            ->size(TextSize::Large),
                        TextEntry::make('advisory.name')
                            ->label(__('resources/advice_thread.columns.advisory.label'))
                            ->icon('heroicon-o-lifebuoy'),
                        TextEntry::make('advice_due_at')
                            ->label(__('resources/advice_thread.columns.advice_due_at.label'))
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('createdBy.name')
                            ->label(__('resources/advice_thread.columns.created_by.label'))
                            ->icon('heroicon-o-user-circle'),
                    ]),

                ThreadMessagesEntry::make('messages'),
            ]);
    }
}
