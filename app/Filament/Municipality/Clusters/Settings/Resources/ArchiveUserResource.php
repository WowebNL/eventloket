<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages\EditArchiveUser;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages\ListArchiveUsers;
use App\Filament\Shared\Pages\EditProfile;
use App\Models\User;
use App\Models\Users\MunicipalityUser;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchiveUserResource extends Resource
{
    protected static ?string $model = MunicipalityUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = Settings::class;

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('municipality/resources/archive_user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/archive_user.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
                Select::make('role')
                    ->label(__('municipality/resources/archive_user.columns.role.label'))
                    ->options(self::roleOptions())
                    ->selectablePlaceholder(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('municipality/resources/archive_user.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('role')
                    ->label(__('municipality/resources/archive_user.columns.role.label'))
                    ->options(self::roleOptions())
                    // An inline editable column saves without consulting the
                    // model policy, so the policy check is applied here.
                    ->disabled(fn (User $record): bool => ! auth()->user()->can('update', $record))
                    ->selectablePlaceholder(false)
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('municipality/resources/archive_user.columns.role.notification'))
                            ->success()
                            ->send();
                    }),
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

    /**
     * The roles this screen may hand out. It manages the archive team, so it
     * offers the two archive roles plus the regular municipality roles an
     * archivist can be moved back to. Handing out the municipality admin roles
     * belongs on the municipality admin screen, not here.
     *
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return [
            Role::ArchiveCoordinator->value => Role::ArchiveCoordinator->getLabel(),
            Role::ArchiveReviewer->value => Role::ArchiveReviewer->getLabel(),
            Role::Reviewer->value => Role::Reviewer->getLabel(),
            Role::Coordinator->value => Role::Coordinator->getLabel(),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @phpstan-ignore-next-line */
        return parent::getEloquentQuery()->archivists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArchiveUsers::route('/'),
            'edit' => EditArchiveUser::route('/{record}/edit'),
        ];
    }
}
