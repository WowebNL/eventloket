<?php

namespace App\Models;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationType;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Users\OrganiserUser;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\Besluit;
use App\ValueObjects\ZGW\BesluitType;
use App\ValueObjects\ZGW\Informatieobject;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

/**
 * @property-read ZaakReferenceData $reference_data
 * @property-read Organisation $organisation
 * @property-read Municipality $municipality
 * @property-read Collection<Informatieobject> $documenten
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

    /** @return BelongsTo<\App\Models\Zaaktype, $this> */
    public function zaaktype(): BelongsTo
    {
        return $this->belongsTo(Zaaktype::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class)->where('type', OrganisationType::Business);
    }

    public function organiserUser(): BelongsTo
    {
        return $this->belongsTo(OrganiserUser::class, 'organiser_user_id', 'id');
    }

    public function organiserThreads()
    {
        return $this->hasMany(OrganiserThread::class)->organiser();
    }

    public function adviceThreads(): HasMany
    {
        return $this->hasMany(AdviceThread::class)->advice();
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

    /**
     * get all the related user to a zaak
     *
     * @return array<User>
     */
    public function relatedUsers(): array
    {
        return array_merge(
            $this->organisation->users->all(),
            $this->adviceThreads->map(function ($thread) {
                /** @var \App\Models\Threads\AdviceThread $thread */
                return $thread->advisory->users->all();
            })->flatten(1)->all(),
            $this->municipality->allReviewerUsers->all()
        );
    }

    protected function eventName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $this->reference_data->naam_evenement ?? $attributes['public_id'],
        );
    }

    /** @return Attribute<\App\ValueObjects\OzZaak, void> */
    protected function openzaak(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return Cache::rememberForever("zaak.{$attributes['id']}.openzaak", function () use ($attributes) {
                    return new OzZaak(...(new Openzaak)->get($attributes['zgw_zaak_url'].'?expand=status,status.statustype,eigenschappen,zaakinformatieobjecten,zaakobjecten,resultaat,resultaat.resultaattype')->all());
                });
            },
            // set: function($value, $attributes) {
            // }
        );
    }

    /** @return Attribute<Collection<Informatieobject>, void> */
    protected function documenten(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (app()->runningInConsole()) {
                    // queue needs documents for adding to mail, skip role filter because this is allready done before job is queued
                    return $this->getDocuments();
                } else {
                    /** @phpstan-ignore-next-line */
                    return $this->getDocuments()->filter(fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role)));
                }
            },
        );
    }

    /** @return Attribute<Collection<Informatieobject>, void> */
    protected function besluitDocumenten(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return $this->besluiten->flatMap(
                    fn (Besluit $besluit) => $besluit->besluitDocumenten->map(
                        fn (Informatieobject $doc) => new Informatieobject(...array_merge($doc->toArray(), ['besluit' => $besluit]))
                    )
                )->flatten();
            },
        );
    }

    /** @return Attribute<Collection<Besluit>, void> */
    protected function besluiten(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                return $this->getBesluiten()->each(function (Besluit $besluit) {
                    $besluit = new Besluit(...array_merge($besluit->toArrayWithObjects(), [
                        /** @phpstan-ignore-next-line */
                        'besluitDocumenten' => $besluit->besluitDocumenten?->filter(fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role))),
                    ]));
                });
            },
        );
    }

    private function getBesluiten(): Collection
    {
        return Cache::rememberForever("zaak.{$this->id}.besluiten", function () {
            $openzaak = new Openzaak;
            $besluiten = $openzaak->besluiten()->besluiten()->getAll(['zaak' => $this->zgw_zaak_url]);
            $collection = collect();
            foreach ($besluiten as $besluit) {
                $besluitDocumentenCollection = collect();
                $besluitInformatieObjecten = $openzaak->besluiten()->besluitinformatieobjecten()->getAll(['besluit' => $besluit['url']]);
                $besluitInformatieObjecten->each(function ($besluitInformatieObject) use ($besluitDocumentenCollection, $openzaak) {
                    $besluitDocumentenCollection->push(new Informatieobject(...$openzaak->get($besluitInformatieObject['informatieobject'])->toArray()));
                });
                $collection->push(new Besluit(...array_merge($besluit, [
                    'besluittypeObject' => new BesluitType(...$openzaak->get($besluit['besluittype'])->toArray()),
                    'besluitDocumenten' => $besluitDocumentenCollection,
                ])));
            }

            return $collection;
        });
    }

    private function getDocuments()
    {
        return Cache::rememberForever("zaak.{$this->id}.documenten", function () {
            $openzaak = new Openzaak;
            $zaakinformatieobjecten = $openzaak->zaken()->zaakinformatieobjecten()->getAll(['zaak' => $this->zgw_zaak_url]);
            $collection = collect();
            foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
                $collection->push(new Informatieobject(...$openzaak->get($zaakinformatieobject['informatieobject'])->toArray()));
            }

            return $collection;
        });
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

    public function clearZgwCache(): void
    {
        Cache::forget("zaak.{$this->id}.openzaak");
        Cache::forget("zaak.{$this->id}.documenten");
        Cache::forget("zaak.{$this->id}.zaakdata");
        Cache::forget("zaak.{$this->id}.besluiten");
    }
}
