<?php

namespace App\Models;

use App\Enums\DocumentVertrouwelijkheden;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\ZGW\InformatieobjectType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    /** @return Attribute<Collection<InformatieobjectType>|null, void> */
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
     * @return Collection<int, InformatieobjectType>
     */
    public function documentTypesForUser(?string $versionUrl = null): Collection
    {
        $types = $this->getDocumentTypes($versionUrl);

        if (auth()->user()) {
            $types = $types->filter(fn (InformatieobjectType $type) => in_array($type->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role)));
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
     * @return Collection<int, InformatieobjectType>
     */
    private function getDocumentTypes(?string $versionUrl = null): Collection
    {
        $connectionName = $this->zgwConnectionName();
        $url = $versionUrl ?: $this->zgw_zaaktype_url;

        return Cache::rememberForever('zaaktype_document_types_'.md5($connectionName.'|'.$url), function () use ($connectionName, $url) {
            $items = Zgw::connection($connectionName)
                ->catalogi()
                ->informatieobjecttypen()
                ->index(['zaaktype' => $url]);
            $collection = collect();
            foreach ($items as $item) {
                $collection->push(new InformatieobjectType(...$item));
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
