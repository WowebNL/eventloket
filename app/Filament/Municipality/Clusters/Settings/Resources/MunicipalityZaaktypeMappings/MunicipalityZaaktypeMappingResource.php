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
            Role::ReviewerMunicipalityAdmin,
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
                            ->options(fn (): array => ZaaktypeCatalogusOptions::zaaktypen(self::connectionName()))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => self::resetDependentFields($set)),
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
            ->map(fn (string $key): Select => Select::make("eigenschap_map.{$key}")
                ->label(Str::headline($key))
                ->options(fn (Get $get): array => self::optionsForSelectedZaaktype(
                    $get,
                    fn (string $conn, string $id) => ZaaktypeCatalogusOptions::eigenschappen($conn, $id),
                ))
                ->searchable()
                ->placeholder(__('municipality/resources/zaaktype_mapping.placeholder')))
            ->all();
    }

    /**
     * A flow-blocker select whose options come from the chosen zaaktype.
     *
     * @param  callable(string, string): array<string, string>  $options
     */
    private static function catalogusSelect(string $field, callable $options): Select
    {
        return Select::make($field)
            ->label(__("municipality/resources/zaaktype_mapping.fields.{$field}.label"))
            ->options(fn (Get $get): array => self::optionsForSelectedZaaktype($get, $options))
            ->searchable()
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
}
