<?php

namespace App\Models;

use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ResolveZaaktype;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;
use Woweb\Zgw\Facades\Zgw;

/**
 * @property ZaaktypeRole|null $role
 */
class Zaaktype extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'zaaktypen';

    protected $fillable = [
        'name',
        'role',
        'identificatie',
        'zgw_zaaktype_url',
        'connection',
        'is_active',
        'hidden_resultaat_types',
        'triggers_route_check',
    ];

    protected function casts(): array
    {
        return [
            'role' => ZaaktypeRole::class,
            'hidden_resultaat_types' => 'array',
            'triggers_route_check' => 'boolean',
            // Cast explicitly: MySQL returns a boolean column as an integer 1/0,
            // so without this the value is not a real bool on that driver.
            'is_active' => 'boolean',
        ];
    }

    public function zaken(): HasMany
    {
        return $this->hasMany(Zaak::class);
    }

    /**
     * The Eventloket role this zaaktype fulfils, resolved with the same ladder
     * {@see ResolveZaaktype} uses to pick a zaaktype for a role, only read in
     * the opposite direction:
     *
     * 1. The municipality's koppeling, a {@see MunicipalityZaaktypeMapping} for
     *    this municipality and identificatie. It is the only reliable source for
     *    a municipality on its own ZGW instance: the local row then carries the
     *    omschrijving of the external zaaktype, which follows no Eventloket
     *    naming convention, so the name says nothing about the role.
     * 2. The stored `role` column, filled by the catalogus syncs.
     * 3. The name prefix of the shared catalogus. Still needed as a last resort:
     *    the shared-catalogus sync only fills `role` when it is still null, so
     *    rows that predate that column keep a null role until a sync or an
     *    admin sets one.
     */
    public function effectiveRole(): ?ZaaktypeRole
    {
        // The ?? already guards the null case with isset semantics, so no
        // nullsafe operator is needed on the left-hand side.
        return MunicipalityZaaktypeMapping::forZaaktype($this)->role
            ?? $this->role
            ?? ZaaktypeRole::fromName($this->name);
    }

    public function isVooraankondiging(): bool
    {
        return $this->effectiveRole() === ZaaktypeRole::Vooraankondiging;
    }

    /**
     * Zaaktypen whose effective role is $role, expressing the ladder of
     * {@see effectiveRole()} in SQL so query-level and model-level checks agree.
     *
     * @param  Builder<Zaaktype>  $query
     * @return Builder<Zaaktype>
     */
    #[Scope]
    protected function withEffectiveRole(Builder $query, ZaaktypeRole $role): Builder
    {
        return $query->where(function (Builder $ladder) use ($role): void {
            $ladder
                ->whereExists(fn (QueryBuilder $sub) => self::mappingsOfZaaktype($sub)->where('municipality_zaaktype_mappings.role', $role->value))
                ->orWhere(function (Builder $withoutKoppeling) use ($role): void {
                    $withoutKoppeling
                        ->whereNotExists(fn (QueryBuilder $sub) => self::mappingsOfZaaktype($sub))
                        ->where(function (Builder $fallback) use ($role): void {
                            $fallback
                                ->where('zaaktypen.role', $role->value)
                                ->orWhere(function (Builder $byName) use ($role): void {
                                    $byName
                                        ->whereNull('zaaktypen.role')
                                        // Lowered on both sides to match the
                                        // case-insensitive prefix check
                                        // {@see ZaaktypeRole::fromName()} does in
                                        // PHP. A plain `like` would not: it is
                                        // case-sensitive on PostgreSQL and
                                        // case-insensitive on MySQL, so the same
                                        // row would answer differently per engine
                                        // and differently from the model.
                                        ->whereRaw('lower(zaaktypen.name) like ?', [mb_strtolower($role->namePrefix()).'%']);
                                });
                        });
                });
        });
    }

    /**
     * Correlated subquery over the koppelingen of the zaaktype row in the outer
     * query. A null municipality_id or identificatie matches nothing, which is
     * what the ladder needs: no koppeling, so fall through to the next step.
     */
    private static function mappingsOfZaaktype(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->select(DB::raw(1))
            ->from('municipality_zaaktype_mappings')
            ->whereColumn('municipality_zaaktype_mappings.municipality_id', 'zaaktypen.municipality_id')
            ->whereColumn('municipality_zaaktype_mappings.zaaktype_identificatie', 'zaaktypen.identificatie');
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The ZGW connection name to use for calls about this zaaktype.
     *
     * The resolver honours the row's `connection` column: a main-catalogus row
     * always resolves to main (also while linked to an own-instance municipality
     * as a fallback), and own-instance rows resolve via their municipality,
     * which also registers the per-municipality connection's runtime config. So
     * catalogus reads and zaak creation stay on the instance that owns the
     * zaaktype URL.
     */
    public function zgwConnectionName(): string
    {
        return app(ZgwConnectionResolver::class)->for($this);
    }

    /**
     * Whether this zaaktype triggers the route check. A municipality that runs
     * its own ZGW instance manages this on its blueprint; everywhere else the
     * admin-managed row value is used. A null override falls back to the row.
     */
    public function effectiveTriggersRouteCheck(): bool
    {
        $override = MunicipalityZaaktypeMapping::forZaaktype($this)?->triggers_route_check;

        return $override ?? (bool) $this->triggers_route_check;
    }

    /**
     * The effective hidden-resultaattype urls per zaaktype id, used by the
     * calendar bulk filter. The admin-managed row value is the base; a
     * per-municipality blueprint value (own-instance gemeenten) overrides it.
     * An explicit empty blueprint value clears the row value for that zaaktype.
     *
     * @return array<string, array<int, string>>
     */
    public static function effectiveHiddenResultaatTypesMap(): array
    {
        $map = [];

        $rows = static::query()
            ->whereNotNull('hidden_resultaat_types')
            ->whereJsonLength('hidden_resultaat_types', '>', 0)
            ->get(['id', 'hidden_resultaat_types']);

        foreach ($rows as $zaaktype) {
            $map[$zaaktype->id] = $zaaktype->hidden_resultaat_types;
        }

        $mappings = MunicipalityZaaktypeMapping::query()
            ->whereNotNull('hidden_resultaat_types')
            ->get();

        foreach ($mappings as $mapping) {
            if (! $mapping->zaaktype_identificatie) {
                continue;
            }

            $zaaktype = static::query()
                ->where('municipality_id', $mapping->municipality_id)
                ->where('identificatie', $mapping->zaaktype_identificatie)
                ->first(['id']);

            if ($zaaktype === null) {
                continue;
            }

            $hidden = $mapping->hidden_resultaat_types;

            if (is_array($hidden) && $hidden !== []) {
                $map[$zaaktype->id] = $hidden;
            } else {
                unset($map[$zaaktype->id]);
            }
        }

        return $map;
    }

    /** @return Attribute<Collection<InformatieObjectTypeData>|null, void> */
    protected function documentTypes(): Attribute
    {
        // TODO: user need to see type in zaakdocumentstable and besluiteninfolist, only need type name there
        return Attribute::make(
            get: fn () => $this->documentTypesForUser(),
        );
    }

    /**
     * Document types for a specific zaaktype version, filtered to what the current
     * user may see. Defaults to this row's (latest) version when no version is given.
     *
     * @return Collection<int, InformatieObjectTypeData>
     */
    public function documentTypesForUser(?string $versionUrl = null): Collection
    {
        $types = $this->catalogusDocumentTypes($versionUrl);

        if (auth()->user()) {
            $allowed = ZgwConnectionConfig::documentVisibilityForRole($this->zgwConnectionName(), auth()->user()->role);
            // The DTO exposes vertrouwelijkheidaanduiding as a backed enum; compare on
            // its value against the allowed string values.
            $types = $types->filter(fn (InformatieObjectTypeData $type) => in_array($type->vertrouwelijkheidaanduiding?->value, $allowed, true));
        }

        return $types;
    }

    /**
     * Every documenttype of this zaaktype version as the catalogus defines it,
     * without the per-user visibility filter.
     *
     * Use this wherever the answer belongs to the connection rather than to the
     * person who happens to trigger it: which documenttype the koppeling
     * configures for an upload is a property of the koppeling, not of what the
     * uploader may see. The queued upload path already resolves it this way,
     * simply because a job has no authenticated user to filter on, so reading it
     * unfiltered keeps both paths answering the same thing.
     *
     * @return Collection<int, InformatieObjectTypeData>
     */
    public function catalogusDocumentTypes(?string $versionUrl = null): Collection
    {
        return $this->getDocumentTypes($versionUrl)->sortBy('omschrijving');
    }

    /** @return Attribute<Collection<array>|null, void> */
    protected function intrekkenResultaatType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->intrekkenResultaatTypeForVersion(),
        );
    }

    /**
     * The "Ingetrokken" resultaattype for a specific zaaktype version.
     *
     * @return array<string, mixed>|null
     */
    public function intrekkenResultaatTypeForVersion(?string $versionUrl = null): ?array
    {
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($this);

        return ZaaktypeBlueprint::ingetrokkenResultaattype($mapping, $this->getResultaatTypen($versionUrl));
    }

    protected function municipalityResultaatTypen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getResultaatTypen()->filter(fn (array $type) => $type['omschrijvingGeneriek'] !== 'Ingetrokken'),
        );
    }

    /**
     * @return Collection<int, InformatieObjectTypeData>
     */
    private function getDocumentTypes(?string $versionUrl = null): Collection
    {
        $connectionName = $this->zgwConnectionName();
        $url = $versionUrl ?: $this->zgw_zaaktype_url;

        // Cache key bumped to v2: the stored DTO type changed from the old
        // InformatieobjectType value object to the package InformatieObjectTypeData.
        return Cache::rememberForever('zaaktype_document_types_v2_'.md5($connectionName.'|'.$url), function () use ($connectionName, $url) {
            // Resolve document types via the standard zaaktype-informatieobjecttypen
            // relation rather than the informatieobjecttypen?zaaktype= filter, which
            // not every ZGW instance supports.
            $relations = Zgw::connection($connectionName)
                ->catalogi()
                ->zaaktypeInformatieobjecttypen()
                ->index(['zaaktype' => $url]);

            $collection = collect();
            // Memo of catalogus url => (omschrijving => informatieobjecttype), so a
            // catalogus is listed at most once per resolution (no per-relation N+1).
            $catalogusMaps = [];

            foreach ($relations as $relation) {
                $value = $relation['informatieobjecttype'] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }

                // The ZGW standard types `informatieobjecttype` as a string:
                // OpenZaak returns a followable URL, while some backends (e.g. RX
                // Mission) return the omschrijving inline. Follow a URL; resolve an
                // omschrijving against its catalogus. A single unresolvable type is
                // skipped (and logged) rather than aborting the whole list — a job
                // that needs a specific type still fails loudly downstream when
                // that type is absent from the resolved list.
                if (str_starts_with($value, 'http')) {
                    try {
                        $collection->push(InformatieObjectTypeData::from(ZgwResource::byUrl($connectionName, $value)));
                    } catch (Throwable $e) {
                        Log::warning('Zaaktype::getDocumentTypes: informatieobjecttype-URL niet op te halen, overgeslagen', [
                            'connection' => $connectionName,
                            'informatieobjecttype' => $value,
                            'exception' => $e->getMessage(),
                        ]);
                    }

                    continue;
                }

                $catalogusUrl = $relation['catalogus'] ?? null;
                if (! is_string($catalogusUrl) || $catalogusUrl === '') {
                    Log::warning('Zaaktype::getDocumentTypes: informatieobjecttype-omschrijving zonder catalogus, overgeslagen', [
                        'connection' => $connectionName,
                        'omschrijving' => $value,
                    ]);

                    continue;
                }

                if (! array_key_exists($catalogusUrl, $catalogusMaps)) {
                    try {
                        $catalogusMaps[$catalogusUrl] = ZgwResource::informatieobjecttypenByOmschrijving($connectionName, $catalogusUrl);
                    } catch (Throwable $e) {
                        // Cache the empty result so a second omschrijving on the same
                        // failing catalogus does not re-hit the API within this run.
                        $catalogusMaps[$catalogusUrl] = [];
                        Log::warning('Zaaktype::getDocumentTypes: informatieobjecttypen van catalogus niet op te halen, overgeslagen', [
                            'connection' => $connectionName,
                            'catalogus' => $catalogusUrl,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }

                $resource = $catalogusMaps[$catalogusUrl][$value] ?? null;
                if ($resource === null) {
                    Log::warning('Zaaktype::getDocumentTypes: informatieobjecttype-omschrijving niet gevonden in catalogus, overgeslagen', [
                        'connection' => $connectionName,
                        'catalogus' => $catalogusUrl,
                        'omschrijving' => $value,
                    ]);

                    continue;
                }

                $collection->push(InformatieObjectTypeData::from($resource));
            }

            return $collection;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getResultaatTypen(?string $versionUrl = null): Collection
    {
        $connectionName = $this->zgwConnectionName();
        $url = $versionUrl ?: $this->zgw_zaaktype_url;

        return Cache::rememberForever('zaaktype_resultaat_typen_'.md5($connectionName.'|'.$url), function () use ($connectionName, $url) {
            return Zgw::connection($connectionName)
                ->catalogi()
                ->resultaattypen()
                ->index(['zaaktype' => $url])
                ->collect();
        });
    }
}
