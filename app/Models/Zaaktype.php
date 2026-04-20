<?php

namespace App\Models;

use App\Enums\DocumentVertrouwelijkheden;
use App\ValueObjects\OzZaaktype;
use App\ValueObjects\ZGW\InformatieobjectType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Woweb\Openzaak\Openzaak;

class Zaaktype extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'zaaktypen';

    protected $fillable = [
        'name',
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

    /** @return Attribute<Collection<InformatieobjectType>|null, void> */
    protected function documentTypes(): Attribute
    {
        // TODO: user need to see type in zaakdocumentstable and besluiteninfolist, only need type name there
        return Attribute::make(
            // get: fn () => $this->getDocumentTypes()->filter(fn (InformatieobjectType $type) => in_array($type->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role)))->sortBy('omschrijving'),
            get: fn () => $this->getDocumentTypes()->sortBy('omschrijving'), // temp disable vertrouwelijkheid check
        );
    }

    /** @return Attribute<Collection<array>|null, void> */
    protected function intrekkenResultaatType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getResultaatTypen()->firstWhere('omschrijvingGeneriek', 'Ingetrokken'),
        );
    }

    protected function municipalityResultaatTypen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getResultaatTypen()->filter(fn (array $type) => $type['omschrijvingGeneriek'] !== 'Ingetrokken'),
        );
    }

    private function getDocumentTypes()
    {
        return Cache::rememberForever('zaaktype_'.$this->id.'_document_types', function () {
            $zaaktype = new OzZaaktype(...(new Openzaak)->get($this->zgw_zaaktype_url)->toArray());
            $collection = collect();
            foreach ($zaaktype->informatieobjecttypen as $item) {
                $collection->push(new InformatieobjectType(...$item));
            }

            return $collection;
        });
    }

    public function getResultaatTypen()
    {
        return Cache::rememberForever('zaaktype_'.$this->id.'_resultaat_typen', function () {
            return (new Openzaak)->catalogi()->resultaattypen()->getAll(['zaaktype' => $this->zgw_zaaktype_url]);
        });
    }
}
