<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\CreateMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\EditMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\ListMunicipalityZgwConnections;
use App\Models\MunicipalityZgwConnection;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Woweb\Zgw\Enums\ZgwVersion;

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ]);
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
