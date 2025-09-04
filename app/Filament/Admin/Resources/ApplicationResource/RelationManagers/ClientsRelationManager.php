<?php

namespace App\Filament\Admin\Resources\ApplicationResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Client;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label(__('admin/resources/client.columns.name.label')),
                TextInput::make('secret')
                    ->required()
                    ->label(__('admin/resources/client.columns.secret.label'))
                    ->maxLength(255)
                    ->password()
                    ->revealable()
                    ->required(fn (string $context) => $context === 'create')
                    ->rule(Password::default())
                    ->validationAttribute(__('filament-panels::auth/pages/register.form.password.validation_attribute'))
                    ->helperText(__('admin/resources/client.columns.secret.helper_text')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/client.columns.name.label')),
                TextColumn::make('id')
                    ->label(__('admin/resources/client.columns.id.label'))
                    ->copyable()
                    ->copyMessage(__('admin/resources/client.columns.id.copy_label')),
                TextColumn::make('active_tokens_count')
                    ->label(__('admin/resources/client.columns.active_tokens_count.label'))
                    ->getStateUsing(function (Client $record): int {
                        return $record->tokens()->where('revoked', false)->where('expires_at', '>', now())->count();
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.created_at.label')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.updated_at.label')),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) {
                        $data['grant_types'] = ['client_credentials'];
                        $data['redirect_uris'] = [];
                        $data['revoked'] = false;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data) {
                        if (empty($data['secret'])) {
                            unset($data['secret']);
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
