<?php

namespace App\Filament\Shared\Resources\ReviewerUsers\Tables;

use App\Models\Users\ReviewerUser;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewerUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/reviewer_user.label'))
            ->pluralModelLabel(__('resources/reviewer_user.plural_label'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/reviewer_user.columns.name.label'))
                    ->description(fn (ReviewerUser $record): string => $record->email)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
