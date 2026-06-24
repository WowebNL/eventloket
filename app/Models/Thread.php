<?php

namespace App\Models;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\OrganiserUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property ThreadType $type
 * @property-read Zaak $zaak
 */
class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'threads';

    protected $fillable = [
        'zaak_id',
        'type',
        'title',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ThreadType::class,
        ];
    }

    public function zaak(): BelongsTo
    {
        return $this->belongsTo(Zaak::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function unreadMessages()
    {
        return $this->messages()->whereHas('unreadByUsers', fn ($query) => $query->where('user_id', auth()->id()));
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_user', 'thread_id', 'user_id');
    }

    /**
     * Returns the model for a specific role
     */
    public static function resolveClassForThreadType(ThreadType $threadType): string
    {
        return match ($threadType) {
            ThreadType::Advice => AdviceThread::class,
            ThreadType::Organiser => OrganiserThread::class,
        };
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (array) $attributes;

        $class = self::resolveClassForThreadType(ThreadType::from($attributes['type']));

        $model = (new $class)->newInstance([], true);

        $model->setRawAttributes($attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    #[Scope]
    protected function advice(Builder $query): void
    {
        $query->where('type', ThreadType::Advice);
    }

    #[Scope]
    protected function organiser(Builder $query): void
    {
        $query->where('type', ThreadType::Organiser);
    }

    public function getParticipants(): Collection
    {
        $threadParticipants = match (get_class($this)) {
            // The advisory only participates while the advice request is active. A
            // concept request has not been sent yet and a finalized one is done, so in
            // both cases the advisory's users are not notified about messages.
            AdviceThread::class => in_array($this->advice_status, AdviceStatus::activeStatuses(), true)
                ? ($this->assignedUsers->count() ? $this->assignedUsers : $this->advisory->adminUsers)
                : collect(),
            OrganiserThread::class => $this->zaak->organisation->users,
            default => collect(),
        };

        // Get municipality users who are participating in this thread:
        // - The user who created the thread (if any)
        // - The user who moved the thread to a handled status
        // - Users who have sent messages in the thread
        $municipalityUserIds = collect();

        if ($this->created_by) {
            $municipalityUserIds->push($this->created_by);
        }

        if ($this->zaak->handled_status_set_by_user_id) {
            $municipalityUserIds->push($this->zaak->handled_status_set_by_user_id);
        }

        $messageUserIds = $this->messages()->pluck('user_id');
        $municipalityUserIds = $municipalityUserIds->merge($messageUserIds)->unique();

        $municipalityReviewerUsers = $this->zaak->municipality->allReviewerUsers()
            ->whereIn('users.id', $municipalityUserIds)
            ->get();

        // If no municipality reviewers are found and the zaak status
        // has just been received or has been finalized, include all municipality reviewers.
        // The statustype is only resolved when no reviewers were found (it can trigger a
        // ZGW lookup), and it can be null when the zaak has no (matching) statustype_url
        // yet, for example for freshly created or imported zaken whose status has not been
        // synced from ZGW. In that case we skip the fallback rather than crash.
        if ($municipalityReviewerUsers->isEmpty()) {
            /** @var \App\ValueObjects\OzStatustype|null $statustype */
            $statustype = $this->zaak->statustype;

            if ($statustype?->isReceived() || $statustype?->isFinalised()) {
                $municipalityReviewerUsers = $this->zaak->municipality->allReviewerUsers()->get();
            }
        }

        return $threadParticipants->merge($municipalityReviewerUsers);
    }

    public function getViewUrlForUser(User $user): string
    {
        $threadType = match (get_class($this)) {
            AdviceThread::class => 'advice-threads',
            OrganiserThread::class => 'organiser-threads',
            default => throw new \Exception('Unknown thread type'),
        };

        $panel = match (get_class($user)) {
            AdvisorUser::class => 'advisor',
            ReviewerUser::class, ReviewerMunicipalityAdminUser::class, MunicipalityAdminUser::class => 'municipality',
            OrganiserUser::class => 'organiser',
            AdminUser::class => 'admin',
            default => throw new \Exception('Unknown receiver class '.get_class($user)),
        };

        $tenant = match (get_class($user)) {
            AdvisorUser::class => $this->advisory_id,
            ReviewerUser::class, ReviewerMunicipalityAdminUser::class, MunicipalityAdminUser::class => $this->zaak->municipality->id,
            OrganiserUser::class => $this->zaak->organisation->uuid,
            default => throw new \Exception('Unknown user class'),
        };

        return route("filament.$panel.resources.zaken.$threadType.view", [
            'tenant' => $tenant,
            'zaak' => $this->zaak_id,
            'record' => $this->id,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
