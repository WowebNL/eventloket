<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concept-opslag voor het evenementformulier. Eén draft per
 * (user, organisation)-combinatie; bevat een FormState-snapshot zodat de
 * gebruiker z'n aanvraag later kan hervatten. Draft wordt gewist bij submit.
 */
class Draft extends Model
{
    protected $table = 'event_form_drafts';

    protected $fillable = [
        'user_id',
        'organisation_id',
        'state',
        'current_step_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
