<?php

namespace App\Models;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;
use App\Observers\ZaakObserver;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\ZGW\Besluit;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;
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
        'hoofdzaak_id',
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
     * The hoofdzaak this zaak belongs to, when it is a doorkomst deelzaak.
     *
     * @return BelongsTo<Zaak, $this>
     */
    public function hoofdzaak(): BelongsTo
    {
        return $this->belongsTo(Zaak::class, 'hoofdzaak_id');
    }

    /**
     * The doorkomst deelzaken created from this (hoofd)zaak. The relationship is
     * tracked locally because ZGW only relates hoofdzaak/deelzaak within a single
     * instance, while doorkomst zaken may live in other municipalities' instances.
     *
     * @return HasMany<Zaak, $this>
     */
    public function deelzaken(): HasMany
    {
        return $this->hasMany(Zaak::class, 'hoofdzaak_id');
    }

    /**
     * The ZGW connection name to use for calls about this zaak.
     */
    public function zgwConnectionName(): string
    {
        return app(ZgwConnectionResolver::class)->for($this);
    }

    /**
     * The per-municipality ZGW connection row, or null when this zaak runs on
     * the global "main" connection (which has no row, hence default behaviour).
     */
    public function zgwConnectionModel(): ?MunicipalityZgwConnection
    {
        return $this->municipality?->zgwConnection;
    }

    /**
     * Whether a behandelaar may change the status (and finish) this zaak inside
     * Eventloket. Locked connections let the municipality drive status in its
     * own system; organiser withdrawal stays possible regardless.
     */
    public function behandelaarCanChangeStatus(): bool
    {
        $connection = $this->zgwConnectionModel();

        return $connection === null || ! $connection->lock_status_for_behandelaar;
    }

    /**
     * Whether a behandelaar may change the risico classificatie (and toelichting)
     * from inside Eventloket. The edit writes these eigenschappen by hardcoded
     * naam and bypasses the per-municipality blueprint, so it is only offered on
     * the global "main" connection; a municipality with its own ZGW connection
     * drives these eigenschappen in its own system.
     */
    public function behandelaarCanEditRisicoClassificatie(): bool
    {
        return $this->zgwConnectionModel() === null;
    }

    /**
     * Whether a given zaak detail tab should be shown for this connection.
     *
     * @param  'besluiten'|'bestanden'|'adviesvragen'|'organisatievragen'  $tab
     */
    public function showsTab(string $tab): bool
    {
        $connection = $this->zgwConnectionModel();

        if ($connection === null) {
            return true;
        }

        return match ($tab) {
            'besluiten' => $connection->show_besluiten_tab,
            'bestanden' => $connection->show_bestanden_tab,
            'adviesvragen' => $connection->show_adviesvragen_tab,
            'organisatievragen' => $connection->show_organisatievragen_tab,
            default => true,
        };
    }

    /**
     * Whether all zaak notifications are suppressed for this connection (only
     * the submission confirmation mail still goes out).
     */
    public function suppressesNotifications(): bool
    {
        $connection = $this->zgwConnectionModel();

        return $connection !== null && $connection->suppress_notifications;
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

    /** @return Attribute<Collection<int, InformatieObjectTypeData>, void> */
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

    /** @return Attribute<ZaakReadModel|null, void> */
    protected function openzaak(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (! $attributes['zgw_zaak_url']) {
                    return null;
                }

                // Cache key bumped to .v2: the cached type changed from the old
                // OzZaak value object to ZaakReadModel.
                return Cache::rememberForever("zaak.{$attributes['id']}.openzaak.v2", function () use ($attributes) {
                    $url = $attributes['zgw_zaak_url'].'?expand=status,status.statustype,eigenschappen,zaakinformatieobjecten,zaakobjecten,resultaat,resultaat.resultaattype';

                    return ZaakReadModel::fromArray(ZgwResource::byUrl($this->zgwConnectionName(), $url));
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
                // Only show finalised documents; concepts from an external ZGW
                // backend are hidden (documents without an explicit status, such
                // as our own uploads, count as final). See Informatieobject::isDefinitief().
                $documenten = $this->getDocuments()->filter(fn (Informatieobject $informatieobject) => $informatieobject->isDefinitief());

                if (app()->runningInConsole()) {
                    // queue needs documents for adding to mail, skip role filter because this is allready done before job is queued
                    return $documenten->values();
                }

                return $this->filterDocumentenForRole($documenten, auth()->user()->role);
            },
        );
    }

    /**
     * Filter a document collection to what the given role may see: the
     * vertrouwelijkheid levels configured (or defaulted) for that role, plus —
     * for an organiser — the documents they submitted themselves, which they may
     * always see regardless of the configured visibility.
     *
     * @param  Collection<int, Informatieobject>  $documenten
     * @return Collection<int, Informatieobject>
     */
    public function filterDocumentenForRole(Collection $documenten, Role $role): Collection
    {
        $allowed = ZgwConnectionConfig::documentVisibilityForRole($this->zgwConnectionName(), $role);

        $ownDocumentUuids = $role === Role::Organiser
            ? $this->organiserSubmittedDocumentUuids()
            : collect();

        return $documenten->filter(
            fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, $allowed)
                || $ownDocumentUuids->contains($informatieobject->uuid)
        )->values();
    }

    /**
     * The uuids of the documents the organiser submitted for this zaak (the
     * aanvraag-PDF and the form bijlagen), identified via the activity log:
     * document-created events on this zaak caused by the zaak's organiser. Used
     * so an organiser always sees their own files regardless of the configured
     * vertrouwelijkheid visibility.
     *
     * @return Collection<int, string>
     */
    private function organiserSubmittedDocumentUuids(): Collection
    {
        if (! $this->organiser_user_id) {
            return collect();
        }

        return Activity::query()
            ->where('log_name', 'document')
            ->where('event', 'created')
            ->where('subject_id', $this->getKey())
            ->where('causer_id', $this->organiser_user_id)
            ->get()
            ->map(fn (Activity $activity) => data_get($activity->properties, 'document_uuid'))
            ->filter(fn ($uuid): bool => is_string($uuid))
            ->values();
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
                // Only show a besluit once it has a finalised besluitdocument and
                // its verzenddatum has been reached. See besluitIsPubliceerbaar().
                return $this->getBesluiten()
                    ->filter(fn (Besluit $besluit) => $this->besluitIsPubliceerbaar($besluit))
                    ->each(function (Besluit $besluit) {
                        $besluit = new Besluit(...array_merge($besluit->toArrayWithObjects(), [
                            'besluitDocumenten' => $besluit->besluitDocumenten?->filter(fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, ZgwConnectionConfig::documentVisibilityForRole($this->zgwConnectionName(), auth()->user()->role))),
                        ]));
                    })
                    ->values();
            },
        );
    }

    /**
     * Whether a besluit may be shown to and notified about: it must have a
     * finalised besluitdocument and a verzenddatum that has been reached (on or
     * before today, Europe/Amsterdam). Besluiten created in Eventloket get a
     * verzenddatum of today, so their behaviour is unchanged.
     */
    private function besluitIsPubliceerbaar(Besluit $besluit): bool
    {
        $heeftDefinitiefDocument = $besluit->besluitDocumenten?->contains(
            fn (Informatieobject $document) => $document->isDefinitief()
        ) ?? false;

        if (! $heeftDefinitiefDocument || empty($besluit->verzenddatum)) {
            return false;
        }

        try {
            return Carbon::parse($besluit->verzenddatum, 'Europe/Amsterdam')
                ->startOfDay()
                ->lessThanOrEqualTo(Carbon::now('Europe/Amsterdam')->startOfDay());
        } catch (\Throwable) {
            return false;
        }
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
                    'besluittypeObject' => BesluitTypeData::from($direct->getByUrl($besluit['besluittype'])),
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
        Cache::forget("zaak.{$this->id}.openzaak.v2");
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
