<?php

namespace App\Filament\Resources\Organisations\RelationManagers;

use App\Enums\OrganisationRole;
use App\Filament\Actions\OrganiserUser\InviteAction;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/resources/organisation.user.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/organisation.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/organisation.user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/organisation.user.form.name.label'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin/resources/organisation.user.form.email.label'))
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->label(__('admin/resources/organisation.user.form.phone.label'))
                    ->maxLength(20),
                Select::make('role')
                    ->label(__('admin/resources/organisation.user.form.role.label'))
                    ->options(OrganisationRole::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('organiser/resources/user.columns.name.label'))
                    ->description(fn (OrganiserUser $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('pivot.role')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->options(OrganisationRole::class)
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (OrganiserUser $record, string $state) {
                        /** @var Organisation $organisation */
                        $organisation = $this->ownerRecord;

                        $record->organisations()->updateExistingPivot($organisation->id, ['role' => $state]);
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                InviteAction::make()
                    ->organisation(fn () => $this->ownerRecord),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
