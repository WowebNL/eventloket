<?php

namespace App\Models;

use App\Enums\MunicipalityFormQuestionType;
use App\Observers\MunicipalityFormQuestionObserver;
use Database\Factories\MunicipalityFormQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra question a municipality adds to the event form, rendered in the
 * "Aanvullende vragen" wizard step. Unlike {@see ReportQuestion} these are
 * purely informative: the answers never influence whether the aanvraag
 * becomes a melding or a vergunning.
 *
 * @property int $id
 * @property int $municipality_id
 * @property int $order
 * @property MunicipalityFormQuestionType $type
 * @property string $label
 * @property string|null $helper_text
 * @property list<string>|null $options
 * @property bool $is_required
 * @property bool $is_active
 * @property list<string>|null $show_for_aanvraag_types
 */
#[ObservedBy(MunicipalityFormQuestionObserver::class)]
class MunicipalityFormQuestion extends Model
{
    /** @use HasFactory<MunicipalityFormQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'order',
        'type',
        'label',
        'helper_text',
        'options',
        'is_required',
        'is_active',
        'show_for_aanvraag_types',
    ];

    protected function casts(): array
    {
        return [
            'type' => MunicipalityFormQuestionType::class,
            'options' => 'array',
            'show_for_aanvraag_types' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The maximum number of questions a single municipality may configure.
     */
    public static function maxPerMunicipality(): int
    {
        return (int) config('extra-questions.max_per_municipality', 15);
    }
}
