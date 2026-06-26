<?php

namespace App\Models;

use App\Enums\AdviceStatus;
use App\Enums\DocumentVertrouwelijkheden;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;
use App\Observers\ZaakObserver;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\Besluit;
use App\ValueObjects\ZGW\BesluitType;
use App\ValueObjects\ZGW\Informatieobject;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;
use Woweb\Zgw\Facades\Zgw;

/**
 * @property ZaakReferenceData $reference_data
 * @property array<string, mixed> $form_state_snapshot
 * @property array<string, mixed>|null $imported_data
 * @property-read ?Organisation                $organisation
 * @property-read ?Municipality                $municipality
 * @property-read Collection<Informatieobject> $documenten
 * @property-read ?StatusTypeData              $statustype
 */
#[ObservedBy(ZaakObserver::class)]
class Zaak extends Model implements Eventable
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'zaken';

    protected $fillable = [
        'public_id',
        'zgw_zaak_url',
        'zaaktype_id',
        'zgw_zaaktype_url',
        'data_object_url',
        'organisation_id',
        'organiser_user_id',
        'reference_data',
        'imported_data',
        'form_state_snapshot',
        'handled_status_set_by_user_id',
        'reviewer_user_id',
    ];

    protected function casts(): array
    {
        return [
            'reference_data' => ZaakReferenceData::class,
            'imported_data' => 'array',
            'form_state_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Zaaktype, $this> */
    public function zaaktype(): BelongsTo
    {
        return $this->belongsTo(Zaaktype::class);
    }

    /**
     * The ZGW connection name to use for calls about this zaak.
     */
    public function zgwConnectionName(): string
    {
        return app(ZgwConnectionResolver::class)->for($this);
    }

    /**
     * The exact zaaktype version url this zaak was created against.
     *
     * Prefers the snapshot column; falls back to the version on the ZGW zaak DTO
     * for rows created before the snapshot existed, and finally to the logical
     * zaaktype's (latest) version url.
     */
    public function zgwZaaktypeVersionUrl(): ?string
    {
        if ($this->zgw_zaaktype_url) {
            return $this->zgw_zaaktype_url;
        }

        // openzaak is only non-null when the zaak has a ZGW url; guard on that so
        // we never dereference a null DTO.
        if ($this->zgw_zaak_url && $this->openzaak->zaaktype) {
            return $this->openzaak->zaaktype;
        }

        return $this->zaaktype?->zgw_zaaktype_url;
    }

    /** @return Attribute<\Illuminate\Support\Collection<int, \App\ValueObjects\ZGW\InformatieobjectType>, void> */
    protected function documentTypes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->zaaktype?->documentTypesForUser($this->zgwZaaktypeVersionUrl()) ?? collect(),
        );
    }

    /** @return Attribute<array<string, mixed>|null, void> */
    protected function intrekkenResultaatType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->zaaktype?->intrekkenResultaatTypeForVersion($this->zgwZaaktypeVersionUrl()),
        );
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<OrganiserUser, $this> */
    public function organiserUser(): BelongsTo
    {
        return $this->belongsTo(OrganiserUser::class, 'organiser_user_id', 'id');
    }

    public function handledStatusSetByUser(): BelongsTo
    {
        return $this->belongsTo(MunicipalityUser::class, 'handled_status_set_by_user_id', 'id');
    }

    /** @return BelongsTo<MunicipalityUser, $this> */
    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(MunicipalityUser::class, 'reviewer_user_id', 'id');
    }

    public function organiserThreads()
    {
        return $this->hasMany(OrganiserThread::class, 'zaak_id')->organiser();
    }

    public function adviceThreads(): HasMany
    {
        return $this->hasMany(AdviceThread::class, 'zaak_id')->advice();
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
        $handlers = $this->getMunicipalityHandlers();

        return array_merge(
            $this->organisation?->users->all() ?? [],
            $this->adviceThreads
                // Only notify advisors while the advice request is active. A concept
                // request has not been sent yet, and a finalized one (approved, rejected,
                // etc.) is done, so in both cases the advisory must no longer be notified.
                ->filter(function ($thread): bool {
                    /** @var AdviceThread $thread */
                    return in_array($thread->advice_status, AdviceStatus::activeStatuses(), true);
                })
                ->map(function ($thread) {
                    /** @var AdviceThread $thread */
                    return $thread->advisory->users->all();
                })->flatten(1)->all(),
            $handlers ? $handlers : []
        );
    }

    /**
     * Returns the municipality-side users to notify for this zaak.
     * Priority: assigned reviewer → coordinators (if present) → all reviewers (fallback).
     */
    public function getMunicipalityHandlers(): array
    {
        if ($this->reviewer_user_id) {
            return [$this->reviewerUser];
        }

        if (! $this->municipality) {
            return [];
        }

        $coordinators = $this->municipality->allCoordinatorUsers()->get();

        if ($coordinators->isNotEmpty()) {
            return $coordinators->all();
        }

        return $this->municipality->allReviewerUsers()->get()->all();
    }

    protected function eventName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $this->reference_data->naam_evenement ?? $attributes['public_id'],
        );
    }

    /** @return Attribute<bool, void> */
    protected function isImported(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => ! $attributes['zgw_zaak_url'] && $attributes['imported_data'] !== null,
        );
    }

    /** @return Attribute<OzZaak, void> */
    protected function openzaak(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (! $attributes['zgw_zaak_url']) {
                    return null;
                }

                return Cache::rememberForever("zaak.{$attributes['id']}.openzaak", function () use ($attributes) {
                    $url = $attributes['zgw_zaak_url'].'?expand=status,status.statustype,eigenschappen,zaakinformatieobjecten,zaakobjecten,resultaat,resultaat.resultaattype';

                    return new OzZaak(...ZgwResource::byUrl($this->zgwConnectionName(), $url));
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
                        'besluitDocumenten' => $besluit->besluitDocumenten?->filter(fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role))),
                    ]));
                });
            },
        );
    }

    private function getBesluiten(): Collection
    {
        if (! $this->zgw_zaak_url) {
            return collect();
        }

        return Cache::rememberForever("zaak.{$this->id}.besluiten", function () {
            $connection = Zgw::connection($this->zgwConnectionName());
            $direct = new DirectEndpoint($connection);
            $besluiten = $connection->besluiten()->besluiten()->index(['zaak' => $this->zgw_zaak_url]);
            $collection = collect();
            foreach ($besluiten as $besluit) {
                $besluitDocumentenCollection = collect();
                $besluitInformatieObjecten = $connection->besluiten()->besluitinformatieobjecten()->index(['besluit' => $besluit['url']]);
                foreach ($besluitInformatieObjecten as $besluitInformatieObject) {
                    $besluitDocumentenCollection->push(new Informatieobject(...ZgwResource::ensureUuid($direct->getByUrl($besluitInformatieObject['informatieobject']))));
                }
                $collection->push(new Besluit(...array_merge($besluit, [
                    'besluittypeObject' => new BesluitType(...ZgwResource::ensureUuid($direct->getByUrl($besluit['besluittype']))),
                    'besluitDocumenten' => $besluitDocumentenCollection,
                ])));
            }

            return $collection;
        });
    }

    private function getDocuments()
    {
        if (! $this->zgw_zaak_url) {
            return collect();
        }

        return Cache::rememberForever("zaak.{$this->id}.documenten", function () {
            $connection = Zgw::connection($this->zgwConnectionName());
            $direct = new DirectEndpoint($connection);
            $zaakinformatieobjecten = $connection->zaken()->zaakinformatieobjecten()->index(['zaak' => $this->zgw_zaak_url]);
            $collection = collect();
            foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
                $collection->push(new Informatieobject(...ZgwResource::ensureUuid($direct->getByUrl($zaakinformatieobject['informatieobject']))));
            }

            return $collection;
        });
    }

    /** @return Attribute<StatusTypeData|null, void> */
    protected function statustype(): Attribute
    {
        return Attribute::make(
            get: function (): ?StatusTypeData {
                // Cache key bumped to .v2 because the stored DTO type changed from
                // the old OzStatustype value object to the package StatusTypeData.
                $statustypen = Cache::remember("statustypen.v2.{$this->zgwConnectionName()}", 60 * 60 * 24, function () {
                    return Zgw::connection($this->zgwConnectionName())
                        ->catalogi()
                        ->statustypen()
                        ->index()
                        ->collect()
                        ->map(fn ($statustype) => StatusTypeData::from($statustype));
                });

                return $statustypen->first(
                    fn (StatusTypeData $statustype) => (string) $statustype->url === $this->reference_data->statustype_url
                );
            },
        );
    }

    /** @return Attribute<?string, void> */
    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => StatusResultaatColor::colorFor($this->reference_data->status_name, $this->reference_data->resultaat),
        );
    }

    public function toCalendarEvent(): CalendarEvent
    {
        // Status tekstueel toevoegen
        $event = CalendarEvent::make($this)
            ->title($this->reference_data->naam_evenement ?? $this->public_id)
            ->start($this->reference_data->start_evenement)
            ->end($this->reference_data->eind_evenement);

        if ($this->status_color) {
            $event->backgroundColor($this->status_color);
        }

        return $event;
    }

    public function clearZgwCache(): void
    {
        Cache::forget("zaak.{$this->id}.openzaak");
        Cache::forget("zaak.{$this->id}.documenten");
        Cache::forget("zaak.{$this->id}.besluiten");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['form_state_snapshot']);
    }
}
