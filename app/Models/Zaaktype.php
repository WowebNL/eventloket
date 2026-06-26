<?php

namespace App\Models;

use App\Enums\DocumentVertrouwelijkheden;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;
use Woweb\Zgw\Facades\Zgw;

class Zaaktype extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'zaaktypen';

    protected $fillable = [
        'name',
        'identificatie',
        'zgw_zaaktype_url',
        'is_active',
        'hidden_resultaat_types',
        'triggers_route_check',
    ];

    protected function casts(): array
    {
        return [
            'hidden_resultaat_types' => 'array',
            'triggers_route_check' => 'boolean',
        ];
    }

    public function zaken(): HasMany
    {
        return $this->hasMany(Zaak::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The ZGW connection name to use for calls about this zaaktype.
     */
    public function zgwConnectionName(): string
    {
        return app(ZgwConnectionResolver::class)->for($this);
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
        $types = $this->getDocumentTypes($versionUrl);

        if (auth()->user()) {
            $allowed = DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role);
            // The DTO exposes vertrouwelijkheidaanduiding as a backed enum; compare on
            // its value against the allowed string values.
            $types = $types->filter(fn (InformatieObjectTypeData $type) => in_array($type->vertrouwelijkheidaanduiding?->value, $allowed, true));
        }

        return $types->sortBy('omschrijving');
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
            // not every ZGW instance supports. Each relation row links to one
            // informatieobjecttype that we then fetch by url.
            $relations = Zgw::connection($connectionName)
                ->catalogi()
                ->zaaktypeInformatieobjecttypen()
                ->index(['zaaktype' => $url]);

            $collection = collect();
            foreach ($relations as $relation) {
                $informatieobjecttypeUrl = $relation['informatieobjecttype'] ?? null;
                if (! is_string($informatieobjecttypeUrl) || $informatieobjecttypeUrl === '') {
                    continue;
                }
                $collection->push(InformatieObjectTypeData::from(ZgwResource::byUrl($connectionName, $informatieobjecttypeUrl)));
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
