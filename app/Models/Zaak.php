<?php

namespace App\Models;

use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\Informatieobject;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Cache;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

/**
 * @property-read ZaakReferenceData $reference_data
 */
class Zaak extends Model implements Eventable
{
    use HasFactory, HasUuids;

    protected $table = 'zaken';

    protected $fillable = [
        'public_id',
        'zgw_zaak_url',
        'zaaktype_id',
        'data_object_url',
        'organisation_id',
        'organiser_user_id',
        'reference_data',
    ];

    protected function casts(): array
    {
        return [
            'reference_data' => ZaakReferenceData::class,
        ];
    }

    public function zaaktype(): BelongsTo
    {
        return $this->belongsTo(Zaaktype::class);
    }

    public function municipality(): HasOneThrough
    {
        return $this->hasOneThrough(
            Municipality::class,
            Zaaktype::class,
            'id',
            'id',
            'zaaktype_id',
            'municipality_id'
        );
    }

    protected function openzaak(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return Cache::rememberForever("zaak.{$attributes['id']}.openzaak", function () use ($attributes) {
                    return new OzZaak(...(new Openzaak)->get($attributes['zgw_zaak_url'].'?expand=status,status.statustype,eigenschappen,zaakinformatieobjecten')->all());
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }

    protected function documenten(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return Cache::rememberForever("zaak.{$attributes['id']}.documenten", function () {
                    $openzaak = new Openzaak;
                    $zaakinformatieobjecten = $openzaak->zaken()->zaakinformatieobjecten()->getAll(['zaak' => $this->zgw_zaak_url]);
                    $collection = collect();
                    foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
                        $collection->push(new Informatieobject(...$openzaak->get($zaakinformatieobject['informatieobject'])->toArray()));
                    }

                    return $collection;
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }

    protected function zaakdata(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return Cache::rememberForever("zaak.{$attributes['id']}.zaakdata", function () use ($attributes) {
                    return new FormSubmissionObject(...(new ObjectsApi)->get(basename($attributes['data_object_url']))->toArray());
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }

    public function toCalendarEvent(): CalendarEvent
    {
        // Status tekstueel toevoegen
        return CalendarEvent::make($this)
            ->title($this->reference_data->naam_evenement ?? $this->public_id)
            ->start($this->reference_data->start_evenement)
            ->end($this->reference_data->eind_evenement);
    }
}
