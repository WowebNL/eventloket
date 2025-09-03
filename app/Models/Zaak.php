<?php

namespace App\Models;

use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\OzZaak;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Cache;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class Zaak extends Model
{
    use HasUuids;

    protected $table = 'zaken';

    protected $fillable = [
        'id',
        'public_id',
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
                    return new OzZaak(...(new Openzaak)->get($attributes['oz_url'].'?expand=status,status.statustype,eigenschappen')->all());
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }

    protected function zaakdata()
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return Cache::rememberForever("zaak.{$attributes['uuid']}.zaakdata", function () use ($attributes) {
                    return (new ObjectsApi)->get($attributes['data_object_url']);
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }
}
