<?php

namespace App\Models;

use App\Observers\MessageObserver;
use App\ValueObjects\MessageDocument;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read Thread $thread
 * @property-read User   $user
 */
#[ObservedBy(MessageObserver::class)]
class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'user_id',
        'body',
        'documents',
    ];

    protected function casts(): array
    {
        return [
            'documents' => AsCollection::of(MessageDocument::class),
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unreadByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unread_messages');
    }
}
