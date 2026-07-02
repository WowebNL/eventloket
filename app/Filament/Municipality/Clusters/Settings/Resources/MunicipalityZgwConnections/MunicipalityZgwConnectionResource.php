<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\CreateMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\EditMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\ListMunicipalityZgwConnections;
use App\Livewire\ConnectionVerifier;
use App\Models\MunicipalityZgwConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->columns(1)
                    ->schema([
                        TextInput::make('zaken_url')->label(__('municipality/resources/zgw_connection.fields.zaken_url.label'))->url()->maxLength(255),
                        TextInput::make('catalogi_url')->label(__('municipality/resources/zgw_connection.fields.catalogi_url.label'))->url()->maxLength(255),
                        TextInput::make('documenten_url')->label(__('municipality/resources/zgw_connection.fields.documenten_url.label'))->url()->maxLength(255),
                        TextInput::make('besluiten_url')->label(__('municipality/resources/zgw_connection.fields.besluiten_url.label'))->url()->maxLength(255),
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
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_besluiten_tab.helper'))
                            ->default(true),
                        Toggle::make('show_bestanden_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_bestanden_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_bestanden_tab.helper'))
                            ->default(true)
                            ->live(),
                        Toggle::make('show_adviesvragen_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_adviesvragen_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_adviesvragen_tab.helper'))
                            ->default(true)
                            ->live(),
                        Toggle::make('show_organisatievragen_tab')
                            ->label(__('municipality/resources/zgw_connection.fields.show_organisatievragen_tab.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.show_organisatievragen_tab.helper'))
                            ->default(true)
                            ->live(),
                        Toggle::make('suppress_notifications')
                            ->label(__('municipality/resources/zgw_connection.fields.suppress_notifications.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.suppress_notifications.helper')),
                        Toggle::make('allow_organiser_withdrawal')
                            ->label(__('municipality/resources/zgw_connection.fields.allow_organiser_withdrawal.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.allow_organiser_withdrawal.helper'))
                            ->default(true),
                    ]),

                Section::make(__('municipality/resources/zgw_connection.sections.vertrouwelijkheid.heading'))
                    ->description(__('municipality/resources/zgw_connection.sections.vertrouwelijkheid.description'))
                    ->columns(1)
                    ->schema([
                        ...self::vertrouwelijkheidRoleFields(),
                        Select::make('vertrouwelijkheid_map.upload_default.system')
                            ->label(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_system_default.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_system_default.helper'))
                            ->options(self::vertrouwelijkheidLevelOptions())
                            ->native(false),
                    ]),
            ]);
    }

    /**
     * One fieldset per document-facing role group, each with the visibility
     * multi select and the upload default. Groups left blank fall back to the
     * hardcoded {@see DocumentVertrouwelijkheden::fromUserRole()} defaults. The
     * municipal handler roles share a single "Gemeente" group; the form binds to
     * the group's canonical role and the choice is fanned out to the other roles
     * on save by {@see pruneVertrouwelijkheidMap()}.
     *
     * @return array<int, Fieldset>
     */
    protected static function vertrouwelijkheidRoleFields(): array
    {
        $levels = self::vertrouwelijkheidLevelOptions();

        return array_map(
            static function (array $group) use ($levels): Fieldset {
                $canonical = $group['roles'][0]->value;

                return Fieldset::make($group['label'])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::documentUploadTabsEnabled($get))
                    ->schema([
                        Select::make("vertrouwelijkheid_map.visibility.{$canonical}")
                            ->label(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_visibility.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_visibility.helper'))
                            ->multiple()
                            ->options($levels)
                            ->native(false),
                        Select::make("vertrouwelijkheid_map.upload_default.{$canonical}")
                            ->label(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_upload_default.label'))
                            ->helperText(__('municipality/resources/zgw_connection.fields.vertrouwelijkheid_upload_default.helper'))
                            ->options($levels)
                            ->native(false),
                    ]);
            },
            self::vertrouwelijkheidGroups(),
        );
    }

    /**
     * The role groups whose document visibility and upload default can be tuned
     * per connection. The first role in each group is the canonical one the form
     * binds to; the others inherit its value on save. Roles outside these groups
     * (platform admin, koppeling beheerder) always fall back to the defaults.
     *
     * @return array<int, array{label: string, roles: array<int, Role>}>
     */
    protected static function vertrouwelijkheidGroups(): array
    {
        return [
            [
                'label' => Role::Organiser->getLabel(),
                'roles' => [Role::Organiser],
            ],
            [
                'label' => Role::Advisor->getLabel(),
                'roles' => [Role::Advisor],
            ],
            [
                'label' => __('municipality/resources/zgw_connection.vertrouwelijkheid_groups.gemeente'),
                'roles' => [
                    Role::Reviewer,
                    Role::Coordinator,
                    Role::MunicipalityAdmin,
                    Role::ReviewerMunicipalityAdmin,
                ],
            ],
        ];
    }

    /**
     * Whether any document upload tab is enabled for this connection. With all
     * three off no role can upload a document through Eventloket, so the per-role
     * visibility and upload defaults are moot and hidden. The system upload
     * default stays relevant: the application PDF and form attachments are still
     * pushed to ZGW regardless of these tabs.
     */
    protected static function documentUploadTabsEnabled(Get $get): bool
    {
        return (bool) $get('show_bestanden_tab')
            || (bool) $get('show_adviesvragen_tab')
            || (bool) $get('show_organisatievragen_tab');
    }

    /**
     * The eight standard ZGW vertrouwelijkheidaanduiding values, by official term.
     *
     * @return array<string, string>
     */
    protected static function vertrouwelijkheidLevelOptions(): array
    {
        $options = [];

        foreach (DocumentVertrouwelijkheden::cases() as $level) {
            $options[$level->value] = __("municipality/resources/zgw_connection.vertrouwelijkheid_levels.{$level->value}");
        }

        return $options;
    }

    /**
     * Fan each role group's canonical choice out onto the roles it represents,
     * then drop empty visibility/upload-default entries so an unconfigured role
     * keeps falling back to the hardcoded defaults instead of being stored as an
     * empty (and therefore "sees nothing") map.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function pruneVertrouwelijkheidMap(array $data): array
    {
        if (! isset($data['vertrouwelijkheid_map']) || ! is_array($data['vertrouwelijkheid_map'])) {
            return $data;
        }

        $map = $data['vertrouwelijkheid_map'];

        foreach (self::vertrouwelijkheidGroups() as $group) {
            $roles = $group['roles'];
            $canonical = $roles[0]->value;
            $members = array_slice($roles, 1);

            foreach (['visibility', 'upload_default'] as $section) {
                $value = $map[$section][$canonical] ?? null;

                foreach ($members as $role) {
                    if ($value === null || $value === '' || $value === []) {
                        unset($map[$section][$role->value]);
                    } else {
                        $map[$section][$role->value] = $value;
                    }
                }
            }
        }

        if (isset($map['visibility']) && is_array($map['visibility'])) {
            $map['visibility'] = array_filter(
                $map['visibility'],
                static fn ($values): bool => is_array($values) && $values !== [],
            );

            if ($map['visibility'] === []) {
                unset($map['visibility']);
            }
        }

        if (isset($map['upload_default']) && is_array($map['upload_default'])) {
            $map['upload_default'] = array_filter(
                $map['upload_default'],
                static fn ($value): bool => is_string($value) && $value !== '',
            );

            if ($map['upload_default'] === []) {
                unset($map['upload_default']);
            }
        }

        $data['vertrouwelijkheid_map'] = $map === [] ? null : $map;

        return $data;
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
                TextColumn::make('activated_at')
                    ->label(__('municipality/resources/zgw_connection.columns.activated_at.label'))
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state
                        ? __('municipality/resources/zgw_connection.columns.activated_at.active')
                        : __('municipality/resources/zgw_connection.columns.activated_at.inactive')),
                TextColumn::make('last_verified_at')
                    ->label(__('municipality/resources/zgw_connection.columns.last_verified_at.label'))
                    ->dateTime(config('app.date_format'))
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state): string => $state
                        ? $state->translatedFormat(config('app.date_format', 'd-m-Y H:i'))
                        : '✕'),
                TextColumn::make('updated_at')
                    ->label(__('municipality/resources/zgw_connection.columns.updated_at.label'))
                    ->dateTime(),
            ])
            ->recordActions([
                self::verifyConnectionAction(),
                self::activateAction(),
                self::deactivateAction(),
            ]);
    }

    /**
     * Activate a connection so the resolver starts routing this municipality's
     * ZGW traffic to it. Only available once the connection has been verified
     * from the "Verbinding testen" modal.
     */
    public static function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('municipality/resources/zgw_connection.actions.activate.label'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (MunicipalityZgwConnection $record): bool => ! $record->isActive())
            ->disabled(fn (MunicipalityZgwConnection $record): bool => $record->last_verified_at === null)
            ->tooltip(fn (MunicipalityZgwConnection $record): ?string => $record->last_verified_at === null
                ? __('municipality/resources/zgw_connection.actions.activate.requires_verification')
                : null)
            ->requiresConfirmation()
            ->modalHeading(__('municipality/resources/zgw_connection.actions.activate.modal_heading'))
            ->modalDescription(__('municipality/resources/zgw_connection.actions.activate.modal_description'))
            ->modalSubmitActionLabel(__('municipality/resources/zgw_connection.actions.activate.confirm'))
            ->authorize(fn (MunicipalityZgwConnection $record): bool => auth()->user()->can('activate', $record))
            ->action(function (MunicipalityZgwConnection $record): void {
                $record->update(['activated_at' => now()]);

                Notification::make()
                    ->success()
                    ->title(__('municipality/resources/zgw_connection.actions.activate.success'))
                    ->send();
            });
    }

    /**
     * Deactivate a connection so the resolver falls back to the "main"
     * connection for this municipality again.
     */
    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label(__('municipality/resources/zgw_connection.actions.deactivate.label'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (MunicipalityZgwConnection $record): bool => $record->isActive())
            ->requiresConfirmation()
            ->modalHeading(__('municipality/resources/zgw_connection.actions.deactivate.modal_heading'))
            ->modalDescription(__('municipality/resources/zgw_connection.actions.deactivate.modal_description'))
            ->modalSubmitActionLabel(__('municipality/resources/zgw_connection.actions.deactivate.confirm'))
            ->authorize(fn (MunicipalityZgwConnection $record): bool => auth()->user()->can('activate', $record))
            ->action(function (MunicipalityZgwConnection $record): void {
                $record->update(['activated_at' => null]);

                Notification::make()
                    ->success()
                    ->title(__('municipality/resources/zgw_connection.actions.deactivate.success'))
                    ->send();
            });
    }

    /**
     * The single "Verbinding testen" row action. It opens a modal that runs the
     * full verification flow (connection, abonnement, notification round trip)
     * via the {@see ConnectionVerifier} component.
     */
    public static function verifyConnectionAction(): Action
    {
        return Action::make('verify')
            ->label(__('municipality/resources/zgw_connection.actions.verify.label'))
            ->icon(Heroicon::OutlinedSignal)
            ->modalHeading(__('municipality/resources/zgw_connection.actions.verify.modal_heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('municipality/resources/zgw_connection.actions.verify.close'))
            ->modalContent(fn (MunicipalityZgwConnection $record) => view(
                'filament.zgw.verify-modal',
                ['connection' => $record],
            ));
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
