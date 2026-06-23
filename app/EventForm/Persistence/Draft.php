<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concept-opslag voor het evenementformulier. Een gebruiker kan meerdere
 * concepten per organisatie hebben (gecapt op DraftStore::MAX_DRAFTS);
 * elk concept bevat een FormState-snapshot zodat de gebruiker z'n aanvraag
 * later kan hervatten. Het actieve concept wordt gewist bij submit.
 *
 * @property string $display_name
 */
class Draft extends Model
{
    protected $table = 'event_form_drafts';

    protected $fillable = [
        'user_id',
        'organisation_id',
        'state',
        'name',
        'current_step_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    /**
     * Weergavenaam voor lijsten en meldingen: de evenementnaam zodra die
     * is ingevuld, anders een herkenbare fallback op aanmaakdatum.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(get: function (): string {
            if (is_string($this->name) && $this->name !== '') {
                return $this->name;
            }

            return 'Concept van '.$this->created_at?->format('d-m-Y');
        });
    }

    /**
     * @param  Builder<Draft>  $query
     * @return Builder<Draft>
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user, Organisation $organisation): Builder
    {
        return $query
            ->where('user_id', $user->id)
            ->where('organisation_id', $organisation->id);
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
