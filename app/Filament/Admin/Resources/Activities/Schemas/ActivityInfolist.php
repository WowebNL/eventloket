<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Phiki\Grammar\Grammar;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('log_name')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('subject_type')
                    ->placeholder('-'),
                TextEntry::make('event')
                    ->placeholder('-'),
                TextEntry::make('subject_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('causer_type')
                    ->placeholder('-'),
                TextEntry::make('causer_id')
                    ->numeric()
                    ->placeholder('-'),
                CodeEntry::make('properties')
                    ->columnSpanFull()
                    ->grammar(Grammar::Json),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
