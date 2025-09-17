<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function messages()
    {
        return $this->hasMany(Message::class, 'thread_id');
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
}
