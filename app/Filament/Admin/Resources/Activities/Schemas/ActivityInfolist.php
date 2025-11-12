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
            ->columns(3)
            ->components([
                TextEntry::make('log_name')
                    ->label(__('resources/activity.infolist.log_name.label'))
                    ->formatStateUsing(fn ($state) => __("activity/log_name.$state"))
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('event')
                    ->label(__('resources/activity.infolist.event.label'))
                    ->formatStateUsing(fn ($state) => __("activity/event.$state"))
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label(__('resources/activity.infolist.created_at.label'))
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->label(__('resources/activity.infolist.description.label'))
                    ->columnSpanFull(),
                TextEntry::make('subject_type')
                    ->label(__('resources/activity.infolist.subject_type.label'))
                    ->placeholder('-'),
                TextEntry::make('subject_id')
                    ->label(__('resources/activity.infolist.subject_id.label'))
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('causer_type')
                    ->label(__('resources/activity.infolist.causer_type.label'))
                    ->placeholder('-'),
                TextEntry::make('causer_id')
                    ->label(__('resources/activity.infolist.causer_id.label'))
                    ->numeric()
                    ->placeholder('-'),
                CodeEntry::make('properties')
                    ->label(__('resources/activity.infolist.properties.label'))
                    ->columnSpanFull()
                    ->grammar(Grammar::Json),
            ]);
    }
}
