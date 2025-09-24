<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Schemas;

use App\Filament\Shared\Infolists\Components\ThreadMessagesEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganiserThreadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label(__('resources/organiser_thread.columns.created_by.label'))
                            ->icon('heroicon-o-user-circle'),
                        TextEntry::make('created_at')
                            ->label(__('resources/organiser_thread.columns.created_at.label'))
                            ->dateTime('M j, Y H:i'),
                    ]),

                ThreadMessagesEntry::make('messages'),
            ]);
    }
}
