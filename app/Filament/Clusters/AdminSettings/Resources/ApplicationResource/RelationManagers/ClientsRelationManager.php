<?php

namespace App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Client;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label(__('admin/resources/client.columns.name.label')),
                Forms\Components\TextInput::make('secret')
                    ->required()
                    ->label(__('admin/resources/client.columns.secret.label'))
                    ->maxLength(255)
                    ->password()
                    ->revealable()
                    ->required(fn (string $context) => $context === 'create')
                    ->rule(Password::default())
                    ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute'))
                    ->helperText(__('admin/resources/client.columns.secret.helper_text')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/resources/client.columns.name.label')),
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin/resources/client.columns.id.label'))
                    ->copyable()
                    ->copyMessage(__('admin/resources/client.columns.id.copy_label')),
                Tables\Columns\TextColumn::make('active_tokens_count')
                    ->label(__('admin/resources/client.columns.active_tokens_count.label'))
                    ->getStateUsing(function (Client $record): int {
                        return $record->tokens()->where('revoked', false)->where('expires_at', '>', now())->count();
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.created_at.label')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.updated_at.label')),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['grant_types'] = ['client_credentials'];
                        $data['redirect_uris'] = [];
                        $data['revoked'] = false;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        if (empty($data['secret'])) {
                            unset($data['secret']);
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
