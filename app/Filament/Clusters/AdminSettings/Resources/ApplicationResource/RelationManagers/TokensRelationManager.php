<?php

namespace App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Laravel\Passport\Token;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin/resources/token.columns.id.label'))
                    ->copyable()
                    ->copyMessage(__('admin/resources/token.columns.id.copy_label')),
                TextColumn::make('client.name')
                    ->label(__('admin/resources/client.columns.name.label')),
                IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->getStateUsing(function (Token $record): bool {
                        return ! $record->revoked && $record->expires_at > now();
                    })
                    ->label(__('admin/resources/token.columns.is_active.label')),
                ToggleColumn::make('revoked')
                    ->label(__('admin/resources/token.columns.revoked.label')),
                TextColumn::make('expires_at')
                    ->since()
                    ->dateTimeTooltip('d-m-Y H:i')
                    ->label(__('admin/resources/token.columns.expires_at.label')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.created_at.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
            ])
            ->toolbarActions([
            ]);
    }
}
