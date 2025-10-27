<?php

namespace App\Models;

use App\Enums\DocumentVertrouwelijkheden;
use App\ValueObjects\ZGW\InformatieobjectType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    public function zaken(): HasMany
    {
        return $this->hasMany(Zaak::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return Attribute<\Illuminate\Support\Collection<\App\ValueObjects\ZGW\InformatieobjectType>|null, void> */
    protected function documentTypes(): Attribute
    {
        // TODO: user need to see type in zaakdocumentstable and besluiteninfolist, only need type name there
        return Attribute::make(
            /** @phpstan-ignore-next-line */
            get: fn () => $this->getDocumentTypes()->filter(fn (InformatieobjectType $type) => in_array($type->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role))),
        );
    }

    /** @return Attribute<\Illuminate\Support\Collection<array>|null, void> */
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
            $items = (new Openzaak)->catalogi()->informatieobjecttypen()->getAll(['zaaktype' => $this->zgw_zaaktype_url]);
            $collection = collect();
            foreach ($items as $item) {
                $collection->push(new InformatieobjectType(...$item));
            }

            return $collection;
        });
    }

    private function getResultaatTypen()
    {
        return Cache::rememberForever('zaaktype_'.$this->id.'_resultaat_typen', function () {
            return (new Openzaak)->catalogi()->resultaattypen()->getAll(['zaaktype' => $this->zgw_zaaktype_url]);
        });
    }
}
