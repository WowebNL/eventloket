<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\CreateMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\EditMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\ListMunicipalityZgwConnections;
use App\Models\MunicipalityZgwConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;
use Woweb\Zgw\Enums\ZgwVersion;
use Woweb\Zgw\Facades\Zgw;

class MunicipalityZgwConnectionResource extends Resource
{
    protected static ?string $model = MunicipalityZgwConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $tenantOwnershipRelationshipName = 'municipality';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            Role::KoppelingBeheerder,
            Role::MunicipalityAdmin,
            Role::ReviewerMunicipalityAdmin,
        ]);
    }

    public static function getModelLabel(): string
    {
        return __('municipality/resources/zgw_connection.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/zgw_connection.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('municipality/resources/zgw_connection.fields.name.label'))
                    ->helperText(__('municipality/resources/zgw_connection.fields.name.helper'))
                    ->maxLength(255)
                    ->columnSpanFull(),

                Section::make(__('municipality/resources/zgw_connection.sections.endpoints.heading'))
                    ->description(__('municipality/resources/zgw_connection.sections.endpoints.description'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('zaken_url')->label(__('municipality/resources/zgw_connection.fields.zaken_url.label'))->url()->maxLength(255),
                        TextInput::make('catalogi_url')->label(__('municipality/resources/zgw_connection.fields.catalogi_url.label'))->url()->maxLength(255),
                        TextInput::make('documenten_url')->label(__('municipality/resources/zgw_connection.fields.documenten_url.label'))->url()->maxLength(255),
                        TextInput::make('besluiten_url')->label(__('municipality/resources/zgw_connection.fields.besluiten_url.label'))->url()->maxLength(255),
                        TextInput::make('autorisaties_url')->label(__('municipality/resources/zgw_connection.fields.autorisaties_url.label'))->url()->maxLength(255),
                        TextInput::make('notificaties_url')->label(__('municipality/resources/zgw_connection.fields.notificaties_url.label'))->url()->maxLength(255),
                    ]),

                Section::make(__('municipality/resources/zgw_connection.sections.authentication.heading'))
                    ->description(__('municipality/resources/zgw_connection.sections.authentication.description'))
                    ->columns(2)
                    ->schema([
                        Select::make('version')
                            ->label(__('municipality/resources/zgw_connection.fields.version.label'))
                            ->options(collect(ZgwVersion::cases())->mapWithKeys(fn (ZgwVersion $v) => [$v->value => $v->label()])->all())
                            ->native(false),
                        TextInput::make('client_id')->label(__('municipality/resources/zgw_connection.fields.client_id.label'))->maxLength(255),
                        TextInput::make('client_secret')
                            ->label(__('municipality/resources/zgw_connection.fields.client_secret.label'))
                            ->password()
                            ->revealable()
                            ->minLength(32)
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? __('municipality/resources/zgw_connection.fields.client_secret.helper_create')
                                : __('municipality/resources/zgw_connection.fields.client_secret.helper_edit')),
                        TextInput::make('user_id')->label(__('municipality/resources/zgw_connection.fields.user_id.label'))->maxLength(255),
                        TextInput::make('user_representation')->label(__('municipality/resources/zgw_connection.fields.user_representation.label'))->maxLength(255),
                    ]),

                Section::make(__('municipality/resources/zgw_connection.sections.parameters.heading'))
                    ->description(__('municipality/resources/zgw_connection.sections.parameters.description'))
                    ->columns(2)
                    ->schema([
                        TagsInput::make('allowed_hosts')
                            ->label(__('municipality/resources/zgw_connection.fields.allowed_hosts.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.allowed_hosts.helper'))
                            ->columnSpanFull(),
                        TextInput::make('bronorganisatie_rsin')
                            ->label(__('municipality/resources/zgw_connection.fields.bronorganisatie_rsin.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.bronorganisatie_rsin.helper'))
                            ->maxLength(9),
                        TextInput::make('eigenschap_date_format')
                            ->label(__('municipality/resources/zgw_connection.fields.eigenschap_date_format.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.eigenschap_date_format.helper'))
                            ->maxLength(32),
                    ]),

                Section::make(__('municipality/resources/zgw_connection.sections.features.heading'))
                    ->description(__('municipality/resources/zgw_connection.sections.features.description'))
                    ->columns(1)
                    ->schema([
                        Toggle::make('lock_status_for_behandelaar')
                            ->label(__('municipality/resources/zgw_connection.fields.lock_status_for_behandelaar.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.lock_status_for_behandelaar.helper')),
                        Toggle::make('show_besluiten_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_besluiten_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_besluiten_tab.helper')),
                        Toggle::make('show_bestanden_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_bestanden_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_bestanden_tab.helper')),
                        Toggle::make('show_adviesvragen_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_adviesvragen_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_adviesvragen_tab.helper')),
                        Toggle::make('show_organisatievragen_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_organisatievragen_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_organisatievragen_tab.helper')),
                        Toggle::make('suppress_notifications')
                            ->label(__('municipality/resources/zgw_connection.fields.suppress_notifications.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.suppress_notifications.helper')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('municipality/resources/zgw_connection.columns.name.label'))
                    ->placeholder('—'),
                TextColumn::make('zaken_url')
                    ->label(__('municipality/resources/zgw_connection.columns.zaken_url.label'))
                    ->placeholder('—')
                    ->limit(50),
                TextColumn::make('version')
                    ->label(__('municipality/resources/zgw_connection.columns.version.label'))
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('municipality/resources/zgw_connection.columns.updated_at.label'))
                    ->dateTime(),
            ])
            ->recordActions([
                self::testConnectionAction(),
            ]);
    }

    /**
     * A row action that runs an end-to-end health check against the connection.
     *
     * It registers this row's config under its runtime name and makes one real
     * read via the package's assertUsable() (a catalogi catalogussen list). The
     * config build also re-applies the secret-length floor, so a weak secret or
     * an unreachable instance both surface as a danger notification.
     */
    public static function testConnectionAction(): Action
    {
        return Action::make('test')
            ->label(__('municipality/resources/zgw_connection.actions.test.label'))
            ->icon(Heroicon::OutlinedSignal)
            ->action(function (MunicipalityZgwConnection $record): void {
                $name = "gemeente_{$record->municipality_id}";

                try {
                    config(["zgw.connections.{$name}" => $record->buildConfig()]);
                    Zgw::connection($name)->assertUsable();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('municipality/resources/zgw_connection.actions.test.failure'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('municipality/resources/zgw_connection.actions.test.success'))
                    ->body(__('municipality/resources/zgw_connection.actions.test.success_body'))
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMunicipalityZgwConnections::route('/'),
            'create' => CreateMunicipalityZgwConnection::route('/create'),
            'edit' => EditMunicipalityZgwConnection::route('/{record}/edit'),
        ];
    }
}
