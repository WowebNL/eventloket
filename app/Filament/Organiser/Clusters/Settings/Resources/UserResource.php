<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources;

use App\Enums\OrganisationRole;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $tenantOwnershipRelationshipName = 'organisations';

    public static function getModelLabel(): string
    {
        return __('organiser/resources/user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organiser/resources/user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('organiser/resources/user.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->getStateUsing(function (User $record) {
                        /** @var \App\Models\Organisation $tenant */
                        $tenant = Filament::getTenant();

                        /** @phpstan-ignore-next-line */
                        $role = $record->organisations->firstWhere('id', $tenant->id)?->pivot->role;

                        return OrganisationRole::from($role);
                    })
                    ->label(__('organiser/resources/user.columns.role.label')),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
