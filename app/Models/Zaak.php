<?php

namespace App\Models;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ZaakRelatieType;
use App\Enums\ZaaktypeRole;
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
use App\ValueObjects\ZGW\ZaakBesluitSet;
use App\ValueObjects\ZGW\ZaakDocumentSet;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;
use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;
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

    /**
     * How long the documents and besluiten read from ZGW are cached.
     *
     * These caches used to be kept forever and relied on ZGW notifications to be
     * invalidated. That is not a safe assumption for an external connection: a
     * subscription that is missing, unreachable or on an unhandled channel meant
     * an empty list read once (before the documents existed) was served
     * indefinitely. A short TTL bounds that failure to a few minutes; explicit
     * invalidation via {@see clearZgwCache()} keeps the common path immediate.
     */
    private const ZGW_READ_CACHE_TTL = 300;

    /**
     * How long an *incomplete* read from ZGW is cached.
     *
     * A screen that shows an incomplete list polls itself, so not caching the
     * gap at all would turn every poll into a fresh round of API calls for as
     * long as the refusal lasts, and a refusal can be a permission setting
     * rather than a passing outage. A short window bounds that traffic and the
     * log volume that comes with it, while keeping the screen's recovery inside
     * a minute once the API hands the resource over again.
     */
    private const ZGW_DEGRADED_READ_CACHE_TTL = 60;

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
     * The per-municipality ZGW connection row this zaak actually runs on, or
     * null when it runs on the global "main" connection (which has no row, hence
     * default behaviour).
     *
     * Gated on the resolved connection name so this never contradicts
     * {@see zgwConnectionName()}, which is what every data call uses. Reading
     * `municipality->zgwConnection` directly would return the municipality's
     * connection even when the zaak reads from main: that happens for a zaak on
     * a main-fallback zaaktype, and for every zaak of a municipality whose
     * connection is not (or no longer) activated. Behaviour flags such as
     * {@see showsTab()} would then describe a different instance than the one
     * the data comes from.
     */
    public function zgwConnectionModel(): ?MunicipalityZgwConnection
    {
        if ($this->zgwConnectionName() === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return null;
        }

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
     * Whether an organiser may withdraw ("intrekken") this zaak from inside
     * Eventloket. Always disabled for a OneGround (RX Mission) connection, where
     * setting the eind-status archives the zaak immediately and is rejected
     * unless all related documents are already 'gearchiveerd'; otherwise it
     * follows the connection's own toggle. The global "main" connection (no row)
     * always allows withdrawal.
     */
    public function organiserCanWithdraw(): bool
    {
        $connection = $this->zgwConnectionModel();

        return $connection === null
            || (! $connection->is_oneground && $connection->allow_organiser_withdrawal);
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

    /**
     * The zaaktype's documenttypes without the per-user visibility filter, for
     * decisions that belong to the koppeling rather than to the current user.
     *
     * @return Collection<int, InformatieObjectTypeData>
     */
    public function catalogusDocumentTypes(): Collection
    {
        return $this->zaaktype?->catalogusDocumentTypes($this->zgwZaaktypeVersionUrl()) ?? collect();
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
     * Generic typed relations where this zaak is the subject. Code outside
     * the datamodel should use the named helpers per type (see
     * vervangtVooraankondiging() / opgevolgdDoor()) instead of these.
     *
     * @return HasMany<ZaakRelatie, $this>
     */
    public function relaties(): HasMany
    {
        return $this->hasMany(ZaakRelatie::class, 'zaak_id');
    }

    /**
     * Generic typed relations where this zaak is the object.
     *
     * @return HasMany<ZaakRelatie, $this>
     */
    public function inverseRelaties(): HasMany
    {
        return $this->hasMany(ZaakRelatie::class, 'gerelateerde_zaak_id');
    }

    /**
     * The vooraankondiging(en) this zaak replaces: read on the definitive
     * aanvraag. Functionally one-to-one, modelled through the generic
     * relation table.
     *
     * @return BelongsToMany<Zaak, $this>
     */
    public function vervangtVooraankondiging(): BelongsToMany
    {
        return $this->belongsToMany(Zaak::class, 'zaak_relaties', 'zaak_id', 'gerelateerde_zaak_id')
            ->wherePivot('type', ZaakRelatieType::VervangtVooraankondiging->value);
    }

    /**
     * The definitive aanvraag that replaced this vooraankondiging. The
     * related model carries SoftDeletes, so a soft-deleted successor is
     * excluded automatically.
     *
     * @return BelongsToMany<Zaak, $this>
     */
    public function opgevolgdDoor(): BelongsToMany
    {
        return $this->belongsToMany(Zaak::class, 'zaak_relaties', 'gerelateerde_zaak_id', 'zaak_id')
            ->wherePivot('type', ZaakRelatieType::VervangtVooraankondiging->value);
    }

    /**
     * Whether this zaak is a vooraankondiging. Single seam: it delegates to the
     * zaaktype, which resolves its role from the municipality's koppeling first
     * and only falls back to the shared-catalogus naming convention.
     */
    public function isVooraankondiging(): bool
    {
        return $this->zaaktype?->isVooraankondiging() ?? false;
    }

    /**
     * @param  Builder<Zaak>  $query
     * @return Builder<Zaak>
     */
    #[Scope]
    protected function vooraankondigingen(Builder $query): Builder
    {
        return $query->whereHas('zaaktype', function (Builder $zaaktypen): Builder {
            /** @var Builder<Zaaktype> $zaaktypen */
            return $zaaktypen->withEffectiveRole(ZaaktypeRole::Vooraankondiging);
        });
    }

    /**
     * Only zaken without a (non-soft-deleted) replacing aanvraag. A
     * vooraankondiging that already has a definitive aanvraag cannot be
     * linked a second time; when that aanvraag is soft-deleted the
     * vooraankondiging becomes linkable again, consistent with the
     * calendar filter.
     *
     * @param  Builder<Zaak>  $query
     * @return Builder<Zaak>
     */
    #[Scope]
    protected function nogNietOpgevolgd(Builder $query): Builder
    {
        return $query->whereDoesntHave('opgevolgdDoor');
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

    /**
     * The documents of this zaak, filtered to what the caller may see.
     *
     * A document the documents API refuses aborts the whole read here. That is
     * deliberate: this is the attribute that mail attachments and queued jobs
     * read, and an attachment list that quietly drops a document is worse than
     * a job that fails. Read-only screens that can tell the reader about the
     * gap opt in to a skipping read through {@see documentenForDisplay()}.
     *
     * @return Attribute<Collection<Informatieobject>, void>
     */
    protected function documenten(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->visibleDocuments($this->getDocuments()),
        );
    }

    /**
     * The documents to show on a zaak detail screen.
     *
     * Unlike the {@see documenten} attribute this leaves out a document the
     * documents API refuses instead of failing, and reports how many were left
     * out, so the screen can show the documents it does have and still say that
     * something is missing.
     */
    public function documentenForDisplay(): ZaakDocumentSet
    {
        $set = $this->readDocuments(skipUnreadable: true);

        return $set->withDocumenten($this->visibleDocuments($set->documenten));
    }

    /**
     * Narrow a raw document list to what the caller may see.
     *
     * @param  Collection<int, Informatieobject>  $documenten
     * @return Collection<int, Informatieobject>
     */
    private function visibleDocuments(Collection $documenten): Collection
    {
        // Only show established documents; concepts from an external ZGW
        // backend are hidden (documents without an explicit status, such
        // as our own uploads, count as established, and so do archived
        // ones). See Informatieobject::isVastgesteld().
        $documenten = $documenten->filter(fn (Informatieobject $informatieobject) => $informatieobject->isVastgesteld());

        if (app()->runningInConsole()) {
            // queue needs documents for adding to mail, skip role filter because this is allready done before job is queued
            return $documenten->values();
        }

        return $this->filterDocumentenForRole($documenten, auth()->user()->role);
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

    /**
     * The besluiten of this zaak, filtered to what the caller may see.
     *
     * As with {@see documenten}, a besluit document the documents API refuses
     * aborts the whole read here, so a caller that needs every document is never
     * quietly handed a shorter list. Read-only screens opt in to a skipping read
     * through {@see besluitenForDisplay()}.
     *
     * @return Attribute<Collection<Besluit>, void>
     */
    protected function besluiten(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->visibleBesluiten($this->getBesluiten()),
        );
    }

    /**
     * The besluiten to show on a zaak detail screen, with the number of besluit
     * documents that could not be read.
     *
     * That number matters more here than it does for the documents tab: a
     * besluit is only publishable once it carries an established document, so a
     * refused document can make the besluit vanish entirely rather than merely
     * shorten its file list.
     */
    public function besluitenForDisplay(): ZaakBesluitSet
    {
        $set = $this->readBesluiten(skipUnreadable: true);

        return $set->withBesluiten($this->visibleBesluiten($set->besluiten));
    }

    /**
     * Narrow a raw besluit list to what the caller may see.
     *
     * @param  Collection<int, Besluit>  $besluiten
     * @return Collection<int, Besluit>
     */
    private function visibleBesluiten(Collection $besluiten): Collection
    {
        // Only show a besluit once its send date has been reached. See
        // besluitIsPubliceerbaar(). This is a publication rule, not a role
        // rule, so it also applies in console context.
        $besluiten = $besluiten
            ->filter(fn (Besluit $besluit) => $this->besluitIsPubliceerbaar($besluit))
            ->values();

        if (app()->runningInConsole()) {
            // Queue and console have no authenticated user; the role filter
            // is applied before the job is queued, mirroring documenten().
            return $besluiten;
        }

        // The besluitdocumenten are filtered to what the current role may
        // see on this connection: map(), not each(), because the value
        // object is readonly and a rebuilt besluit has to replace the
        // original one. each() returns the collection unchanged, which
        // would leave every role with all besluitdocumenten.
        $allowed = ZgwConnectionConfig::documentVisibilityForRole($this->zgwConnectionName(), auth()->user()->role);

        return $besluiten
            ->map(fn (Besluit $besluit) => new Besluit(...array_merge($besluit->toArrayWithObjects(), [
                'besluitDocumenten' => $besluit->besluitDocumenten
                    ?->filter(fn (Informatieobject $informatieobject) => in_array($informatieobject->vertrouwelijkheidaanduiding, $allowed))
                    ->values(),
            ])))
            ->values();
    }

    /**
     * Whether a besluit may be shown to and notified about: its send date must
     * have been reached (on or before today, Europe/Amsterdam) and it must carry
     * an established besluitdocument. Besluiten created in Eventloket get a
     * verzenddatum of today and a document, so their behaviour is unchanged.
     *
     * A besluit taken in the ZGW backend itself need not have a
     * besluitinformatieobject linked to it at all: on a OneGround (RX Mission)
     * connection the decision document is commonly kept as a zaakdocument. The
     * document requirement is therefore lifted for those connections, where the
     * send date alone decides. Requiring it there hid every such besluit
     * indefinitely.
     */
    private function besluitIsPubliceerbaar(Besluit $besluit): bool
    {
        $verzenddatum = $this->besluitVerzenddatum($besluit);
        if ($verzenddatum === null) {
            return false;
        }

        if (! $this->besluitHeeftVastgesteldDocument($besluit) && ! ZgwConnectionConfig::isOneGround($this->zgwConnectionName())) {
            return false;
        }

        try {
            return Carbon::parse($verzenddatum, 'Europe/Amsterdam')
                ->startOfDay()
                ->lessThanOrEqualTo(Carbon::now('Europe/Amsterdam')->startOfDay());
        } catch (Throwable) {
            return false;
        }
    }

    private function besluitHeeftVastgesteldDocument(Besluit $besluit): bool
    {
        return $besluit->besluitDocumenten?->contains(
            fn (Informatieobject $document) => $document->isVastgesteld()
        ) ?? false;
    }

    /**
     * The date that decides when a besluit becomes visible. `verzenddatum` is
     * optional in the ZGW Besluiten API; `publicatiedatum` is the fallback for a
     * besluit that was published without one.
     */
    private function besluitVerzenddatum(Besluit $besluit): ?string
    {
        if (! empty($besluit->verzenddatum)) {
            return $besluit->verzenddatum;
        }

        $publicatiedatum = $besluit->otherParams['publicatiedatum'] ?? null;

        return is_string($publicatiedatum) && $publicatiedatum !== '' ? $publicatiedatum : null;
    }

    /**
     * Every besluit of the zaak, unfiltered, failing on the first document that
     * cannot be read.
     *
     * @return Collection<int, Besluit>
     */
    private function getBesluiten(): Collection
    {
        return $this->readBesluiten(skipUnreadable: false)->besluiten;
    }

    /**
     * Read the besluiten of this zaak, with their documents.
     *
     * The per-document step is the same call on the same endpoint as in
     * {@see readDocuments()}, so it carries the same containment: with
     * $skipUnreadable a document the API refuses is counted and left out instead
     * of aborting the read.
     *
     * Two reads around it deliberately stay strict, because skipping them means
     * something else than "one file is missing". The besluittype is not a
     * document but the definition the besluit is built from, so a besluit
     * without it cannot be constructed at all; and a failing list call means the
     * besluiten are unknown rather than incomplete.
     */
    private function readBesluiten(bool $skipUnreadable): ZaakBesluitSet
    {
        if (! $this->zgw_zaak_url) {
            return new ZaakBesluitSet(collect());
        }

        $cacheKey = "zaak.{$this->id}.besluiten";
        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return new ZaakBesluitSet($cached);
        }

        if ($cached instanceof ZaakBesluitSet && $skipUnreadable) {
            return $cached;
        }

        $connectionName = $this->zgwConnectionName();
        $connection = Zgw::connection($connectionName);
        $direct = new DirectEndpoint($connection);
        $besluiten = $connection->besluiten()->besluiten()->index(['zaak' => $this->zgw_zaak_url]);

        $collection = collect();
        $unreadable = 0;

        foreach ($besluiten as $besluit) {
            $besluitDocumentenCollection = collect();
            $besluitInformatieObjecten = $connection->besluiten()->besluitinformatieobjecten()->index(['besluit' => $besluit['url']]);

            foreach ($besluitInformatieObjecten as $besluitInformatieObject) {
                $documentUrl = $besluitInformatieObject['informatieobject'];

                try {
                    $besluitDocumentenCollection->push(new Informatieobject(...ZgwResource::ensureUuid($direct->getByUrl($documentUrl))));
                } catch (DisallowedHostException $e) {
                    // See readDocuments(): an origin the connection does not trust
                    // is a trust boundary and stays loud.
                    throw $e;
                } catch (Throwable $e) {
                    if (! $skipUnreadable) {
                        throw $e;
                    }

                    $unreadable++;
                    $this->reportSkippedResource('besluit document', $connectionName, $documentUrl, $e);
                }
            }

            $collection->push(new Besluit(...array_merge($besluit, [
                'besluittypeObject' => BesluitTypeData::from($direct->getByUrl($besluit['besluittype'])),
                'besluitDocumenten' => $besluitDocumentenCollection,
            ])));
        }

        if ($unreadable === 0) {
            Cache::put($cacheKey, $collection, self::ZGW_READ_CACHE_TTL);

            return new ZaakBesluitSet($collection);
        }

        $set = new ZaakBesluitSet($collection, $unreadable);
        Cache::put($cacheKey, $set, self::ZGW_DEGRADED_READ_CACHE_TTL);

        return $set;
    }

    /**
     * Every document the zaak holds, unfiltered, failing on the first one that
     * cannot be read.
     *
     * @return Collection<int, Informatieobject>
     */
    private function getDocuments(): Collection
    {
        return $this->readDocuments(skipUnreadable: false)->documenten;
    }

    /**
     * Read the documents of this zaak from the documents API.
     *
     * Documents are listed in one call and then fetched one by one, and a
     * documents API may well hand over the list while refusing an individual
     * document. With $skipUnreadable such a document is counted and left out
     * instead of aborting the read, so one document cannot take a whole screen
     * with it; without it the failure propagates, which is what a caller that
     * needs every document wants.
     *
     * An incomplete read is cached with a shorter TTL than a complete one, see
     * self::ZGW_DEGRADED_READ_CACHE_TTL. The screens that accept a gap refresh
     * themselves every few seconds, so not caching it at all would put a fresh
     * round of API calls behind every refresh for as long as the refusal lasts,
     * while caching it for the normal window would leave the screen reporting
     * missing documents long after the API started handing them over again. The
     * short window bounds both.
     */
    private function readDocuments(bool $skipUnreadable): ZaakDocumentSet
    {
        if (! $this->zgw_zaak_url) {
            return new ZaakDocumentSet(collect());
        }

        $cacheKey = "zaak.{$this->id}.documenten";
        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return new ZaakDocumentSet($cached, $cached->count());
        }

        // An incomplete read is cached as the set itself rather than as a plain
        // collection, so the type says what it is. A caller that needs every
        // document therefore reads straight past it and fails on the API, which
        // is what it asked for; only a caller that accepts a gap may have it.
        if ($cached instanceof ZaakDocumentSet && $skipUnreadable) {
            return $cached;
        }

        $connectionName = $this->zgwConnectionName();
        $connection = Zgw::connection($connectionName);
        $direct = new DirectEndpoint($connection);
        $zaakinformatieobjecten = $connection->zaken()->zaakinformatieobjecten()->index(['zaak' => $this->zgw_zaak_url]);

        $collection = collect();
        $unreadable = 0;

        foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
            $documentUrl = $zaakinformatieobject['informatieobject'];

            try {
                $collection->push(new Informatieobject(...ZgwResource::ensureUuid($direct->getByUrl($documentUrl))));
            } catch (DisallowedHostException $e) {
                // Not a document we could not read but a url we refuse to call:
                // the connection's allowlist rejected the origin the list points
                // at. That is a trust boundary, not an availability problem
                // outside our control, so it keeps the loud failure the guard was
                // built to produce instead of being softened into a notice.
                throw $e;
            } catch (Throwable $e) {
                if (! $skipUnreadable) {
                    throw $e;
                }

                $unreadable++;
                $this->reportSkippedResource('document', $connectionName, $documentUrl, $e);
            }
        }

        if ($unreadable === 0) {
            Cache::put($cacheKey, $collection, self::ZGW_READ_CACHE_TTL);

            return new ZaakDocumentSet($collection, $collection->count());
        }

        $set = new ZaakDocumentSet($collection, $collection->count(), $unreadable);
        Cache::put($cacheKey, $set, self::ZGW_DEGRADED_READ_CACHE_TTL);

        return $set;
    }

    /**
     * Record a resource that was skipped, twice over.
     *
     * The log line carries enough to tell a refusal apart from an outage: which
     * resource, the HTTP status and the error code the API returned. The
     * response body stays out of it on purpose, because a documents API answer
     * can carry the resource's own metadata.
     *
     * Reporting it as well is what keeps the degradation visible. Before the
     * containment the failure surfaced as an error report on its own; a log line
     * would not replace that, because the log stack has no reporting channel in
     * it. Going through report() also reuses the handler that attaches the ZGW
     * response as context, which is the detail that makes such a refusal
     * diagnosable at all.
     *
     * @param  string  $kind  what was skipped, for the log line
     */
    private function reportSkippedResource(string $kind, string $connectionName, string $url, Throwable $e): void
    {
        $response = $e instanceof ApiRequestException ? $e->getResponse() : null;
        $body = $response?->json();
        $code = is_array($body) ? ($body['code'] ?? null) : null;

        Log::warning("A zaak {$kind} could not be read and was left out of the list.", [
            'zaak_id' => $this->id,
            'connection' => $connectionName,
            'kind' => $kind,
            'url' => $url,
            'status' => $response?->status(),
            'code' => is_scalar($code) ? (string) $code : null,
            'exception' => $e::class,
        ]);

        report($e);
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
            ->title($this->reference_data->naam_evenement ?? $this->public_id);

        // The evenement dates are optional (the zaaktype does not have to carry
        // those eigenschappen) and CalendarEvent::start()/end() do not accept
        // null. The month query already filters on start_evenement, so a zaak
        // without dates simply stays out of the calendar.
        if ($this->reference_data->start_evenement !== null) {
            $event->start($this->reference_data->start_evenement);
        }

        if ($this->reference_data->eind_evenement !== null) {
            $event->end($this->reference_data->eind_evenement);
        }

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
