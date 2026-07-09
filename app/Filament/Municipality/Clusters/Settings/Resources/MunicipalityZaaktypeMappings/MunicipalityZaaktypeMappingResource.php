<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings;

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\CreateMunicipalityZaaktypeMapping;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\EditMunicipalityZaaktypeMapping;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\ListMunicipalityZaaktypeMappings;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Services\Zgw\ZaaktypeCatalogusOptions;
use App\Services\Zgw\ZgwConnectionResolver;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class MunicipalityZaaktypeMappingResource extends Resource
{
    protected static ?string $model = MunicipalityZaaktypeMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 4;

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
        return __('municipality/resources/zaaktype_mapping.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/zaaktype_mapping.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('municipality/resources/zaaktype_mapping.sections.zaaktype.heading'))
                    ->description(__('municipality/resources/zaaktype_mapping.sections.zaaktype.description'))
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label(__('municipality/resources/zaaktype_mapping.fields.role.label'))
                            ->options(collect(ZaaktypeRole::cases())->mapWithKeys(fn (ZaaktypeRole $r) => [$r->value => Str::headline($r->value)])->all())
                            ->required()
                            ->native(false)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('municipality_id', Filament::getTenant()?->getKey())),
                        Select::make('zaaktype_identificatie')
                            ->label(__('municipality/resources/zaaktype_mapping.fields.zaaktype_identificatie.label'))
                            ->searchable()
                            // A closure keeps this a dynamic select, so Filament
                            // loads its options when the dropdown opens (and filters
                            // client-side) instead of showing a bare search box. The
                            // list is skipped on the initial page render so the
                            // catalogi are only read on interaction; the selected
                            // label is still resolved cheaply below.
                            ->options(fn (): array => self::deferCatalogiRead()
                                ? []
                                : ZaaktypeCatalogusOptions::zaaktypen(self::connectionName()))
                            ->getOptionLabelUsing(fn (?string $value): ?string => self::labelFromOptions(
                                ZaaktypeCatalogusOptions::zaaktypen(self::connectionName()),
                                $value,
                            ))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => self::resetDependentFields($set)),
                    ]),

                Section::make(__('municipality/resources/zaaktype_mapping.sections.behaviour.heading'))
                    ->description(__('municipality/resources/zaaktype_mapping.sections.behaviour.description'))
                    ->columns(1)
                    ->visible(fn (Get $get): bool => self::hasOwnConnection() && filled($get('zaaktype_identificatie')))
                    ->schema([
                        Checkbox::make('triggers_route_check')
                            ->label(__('municipality/resources/zaaktype_mapping.fields.triggers_route_check.label'))
                            ->helperText(__('municipality/resources/zaaktype_mapping.fields.triggers_route_check.helper_text')),
                        CheckboxList::make('hidden_resultaat_types')
                            ->label(__('municipality/resources/zaaktype_mapping.fields.hidden_resultaat_types.label'))
                            ->helperText(__('municipality/resources/zaaktype_mapping.fields.hidden_resultaat_types.helper_text'))
                            ->options(fn (Get $get): array => self::optionsForSelectedZaaktype(
                                $get,
                                fn (string $conn, string $id) => ZaaktypeCatalogusOptions::resultaattypenByUrl($conn, $id),
                            ))
                            ->columnSpanFull(),
                    ]),

                Fieldset::make(__('municipality/resources/zaaktype_mapping.sections.eigenschappen.heading'))
                    ->columns(3)
                    ->visible(fn (Get $get): bool => filled($get('zaaktype_identificatie')))
                    ->schema(self::eigenschapFields()),

                Section::make(__('municipality/resources/zaaktype_mapping.sections.flow.heading'))
                    ->description(__('municipality/resources/zaaktype_mapping.sections.flow.description'))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('zaaktype_identificatie')))
                    ->schema([
                        self::catalogusSelect('initial_statustype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::statustypen($conn, $id)),
                        self::catalogusSelect('eind_statustype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::statustypen($conn, $id))
                            ->visible(fn (): bool => self::organiserWithdrawalAllowed()),
                        self::catalogusSelect('initiator_roltype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::roltypen($conn, $id)),
                        self::catalogusSelect('ingetrokken_resultaattype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::resultaattypen($conn, $id))
                            ->visible(fn (): bool => self::organiserWithdrawalAllowed()),
                        self::catalogusSelect('aanvraag_informatieobjecttype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::informatieobjecttypen($conn, $id)),
                        self::catalogusSelect('bijlage_informatieobjecttype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::informatieobjecttypen($conn, $id)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->label(__('municipality/resources/zaaktype_mapping.columns.role.label'))
                    ->badge()
                    ->formatStateUsing(fn (ZaaktypeRole $state): string => Str::headline($state->value)),
                TextColumn::make('zaaktype_identificatie')
                    ->label(__('municipality/resources/zaaktype_mapping.columns.zaaktype_identificatie.label'))
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('municipality/resources/zaaktype_mapping.columns.updated_at.label'))
                    ->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMunicipalityZaaktypeMappings::route('/'),
            'create' => CreateMunicipalityZaaktypeMapping::route('/create'),
            'edit' => EditMunicipalityZaaktypeMapping::route('/{record}/edit'),
        ];
    }

    /**
     * Drop the empty eigenschap selects so the stored map only contains the
     * keys the beheerder actually mapped (the blueprint treats a missing key
     * and a null value identically).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function pruneEigenschapMap(array $data): array
    {
        if (isset($data['eigenschap_map']) && is_array($data['eigenschap_map'])) {
            $map = array_filter($data['eigenschap_map'], fn ($value): bool => $value !== null && $value !== '');
            $data['eigenschap_map'] = $map === [] ? null : $map;
        }

        return $data;
    }

    /**
     * One select per logical eigenschap key, mapping it to an eigenschap naam
     * of the chosen zaaktype. Stored into the eigenschap_map json via dot paths.
     *
     * @return array<int, Select>
     */
    private static function eigenschapFields(): array
    {
        return collect(ZaakeigenschappenMap::logicalKeys())
            ->map(fn (string $key): Select => self::catalogusSelect(
                "eigenschap_map.{$key}",
                fn (string $conn, string $id) => ZaaktypeCatalogusOptions::eigenschappen($conn, $id),
                label: Str::headline($key),
            ))
            ->all();
    }

    /**
     * A select whose options come from the chosen zaaktype's catalogi. A closure
     * keeps it a dynamic select, so Filament loads the (cached) catalogi list when
     * the dropdown opens and filters it client-side, so opening the select shows
     * the full list rather than an empty search box. The list is skipped on the
     * initial page render (see {@see deferCatalogiRead()}) so rendering the form
     * does not read the catalogi; the stored value is itself a readable label
     * (omschrijving / naam), so the already-selected option still shows.
     *
     * @param  callable(string, string): array<string, string>  $options
     */
    private static function catalogusSelect(string $field, callable $options, ?string $label = null): Select
    {
        return Select::make($field)
            ->label($label ?? __("municipality/resources/zaaktype_mapping.fields.{$field}.label"))
            ->searchable()
            ->options(fn (Get $get): array => self::deferCatalogiRead()
                ? []
                : self::optionsForSelectedZaaktype($get, $options))
            ->getOptionLabelUsing(fn (?string $value): ?string => ($value === null || $value === '') ? null : $value)
            ->placeholder(__('municipality/resources/zaaktype_mapping.placeholder'));
    }

    /**
     * Whether to skip reading the catalogi for a select's options right now.
     *
     * The options closures make each select dynamic so Filament fetches them when
     * the dropdown is opened. On the initial full-page render there is no such
     * interaction yet, so we return an empty list and avoid a burst of catalogi
     * reads on load. Livewire sends the "X-Livewire" header on its interaction
     * requests (including the dropdown-open options fetch), so its presence marks
     * a moment where loading the list is both wanted and worthwhile.
     */
    private static function deferCatalogiRead(): bool
    {
        return ! request()->hasHeader('X-Livewire');
    }

    /**
     * Run an option builder for the currently selected zaaktype, or return an
     * empty list when none is chosen yet.
     *
     * @param  callable(string, string): array<string, string>  $builder
     * @return array<string, string>
     */
    private static function optionsForSelectedZaaktype(Get $get, callable $builder): array
    {
        $identificatie = $get('zaaktype_identificatie');

        return is_string($identificatie) && $identificatie !== ''
            ? $builder(self::connectionName(), $identificatie)
            : [];
    }

    /**
     * Resolve a single option's label from a (cached) options map, falling back
     * to the raw value so a selected option still shows when the list is
     * momentarily unavailable.
     *
     * @param  array<string, string>  $options
     */
    private static function labelFromOptions(array $options, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $options[$value] ?? $value;
    }

    private static function resetDependentFields(Set $set): void
    {
        $set('eigenschap_map', null);

        foreach (['initial_statustype', 'eind_statustype', 'initiator_roltype', 'ingetrokken_resultaattype', 'bijlage_informatieobjecttype'] as $field) {
            $set($field, null);
        }
    }

    /**
     * The connection whose catalogi drive the option lists. This is a management
     * surface, so it reads the municipality's own connection even before it is
     * activated (activation only gates whether the runtime path uses it for
     * submissions), letting a koppeling be configured up front.
     */
    private static function connectionName(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Municipality
            ? app(ZgwConnectionResolver::class)->forManagement($tenant)
            : 'main';
    }

    /**
     * Whether the current municipality runs its own ZGW instance. The route-check
     * and hidden-results overrides only make sense there; municipalities on the
     * shared main connection keep these settings admin-managed on the zaaktype row.
     */
    private static function hasOwnConnection(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Municipality && $tenant->zgwConnection !== null;
    }

    /**
     * Whether an organiser may withdraw a zaak on this municipality's connection.
     * When disabled the eind-statustype and ingetrokken-resultaattype do not need
     * to be configured, so those two flow selects are hidden. Always disabled for
     * a OneGround connection; otherwise it follows the connection's own toggle.
     * The global "main" connection (no row) always allows withdrawal.
     */
    private static function organiserWithdrawalAllowed(): bool
    {
        $tenant = Filament::getTenant();
        $connection = $tenant instanceof Municipality ? $tenant->zgwConnection : null;

        return $connection === null
            || (! $connection->is_oneground && $connection->allow_organiser_withdrawal);
    }
}
