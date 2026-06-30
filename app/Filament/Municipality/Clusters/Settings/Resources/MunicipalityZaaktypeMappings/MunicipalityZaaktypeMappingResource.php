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
                            // Load the (cached) zaaktypen list only when the user
                            // opens the dropdown; on first render we just resolve
                            // the label of the already-selected identificatie.
                            ->getSearchResultsUsing(fn (?string $search): array => self::filterOptions(
                                ZaaktypeCatalogusOptions::zaaktypen(self::connectionName()),
                                $search,
                            ))
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
                        self::catalogusSelect('eind_statustype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::statustypen($conn, $id)),
                        self::catalogusSelect('initiator_roltype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::roltypen($conn, $id)),
                        self::catalogusSelect('ingetrokken_resultaattype', fn (string $conn, string $id) => ZaaktypeCatalogusOptions::resultaattypen($conn, $id)),
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
     * A select whose options come from the chosen zaaktype's catalogi. The
     * (cached) catalogi list is fetched lazily — only when the user opens the
     * dropdown — so rendering the edit form does not block on a burst of ZGW
     * reads. The stored value is itself a readable label (omschrijving / naam),
     * so the already-selected option shows without a remote read on load.
     *
     * @param  callable(string, string): array<string, string>  $options
     */
    private static function catalogusSelect(string $field, callable $options, ?string $label = null): Select
    {
        return Select::make($field)
            ->label($label ?? __("municipality/resources/zaaktype_mapping.fields.{$field}.label"))
            ->searchable()
            ->getSearchResultsUsing(fn (Get $get, ?string $search): array => self::filterOptions(
                self::optionsForSelectedZaaktype($get, $options),
                $search,
            ))
            ->getOptionLabelUsing(fn (?string $value): ?string => ($value === null || $value === '') ? null : $value)
            ->placeholder(__('municipality/resources/zaaktype_mapping.placeholder'));
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
     * Filter an options map by the user's search term (case-insensitive, on both
     * the stored value and its label). An empty search returns the full list.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function filterOptions(array $options, ?string $search): array
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return $options;
        }

        $needle = Str::lower($search);

        return array_filter(
            $options,
            fn (string $label, string $value): bool => str_contains(Str::lower($label), $needle)
                || str_contains(Str::lower($value), $needle),
            ARRAY_FILTER_USE_BOTH,
        );
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

    private static function connectionName(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Municipality ? $tenant->zgwConnectionName() : 'main';
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
}
