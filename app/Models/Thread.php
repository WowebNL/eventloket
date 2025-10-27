<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Users\AdvisorUser;
use App\Models\Users\OrganiserUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property ThreadType $type
 * @property-read Zaak $zaak
 */
class Thread extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadFactory> */
    use HasFactory;

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
            AdviceThread::class => $this->assignedUsers->count() ? $this->assignedUsers : $this->advisory->users,
            OrganiserThread::class => $this->zaak->organisation->users,
            default => collect(),
        };

        $municipalityReviewerUsers = $this->zaak->municipality->allReviewerUsers;

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
            ReviewerUser::class, ReviewerMunicipalityAdminUser::class => 'municipality',
            OrganiserUser::class => 'organiser',
            default => throw new \Exception('Unknown receiver class'),
        };

        $tenant = match (get_class($user)) {
            AdvisorUser::class => $this->advisory_id,
            ReviewerUser::class, ReviewerMunicipalityAdminUser::class => $this->zaak->municipality->id,
            OrganiserUser::class => $this->zaak->organisation->uuid,
            default => throw new \Exception('Unknown user class'),
        };

        return route("filament.$panel.resources.zaken.$threadType.view", [
            'tenant' => $tenant,
            'zaak' => $this->zaak_id,
            'record' => $this->id,
        ]);
    }
}
