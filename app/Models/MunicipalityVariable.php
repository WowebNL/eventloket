<?php

namespace App\Models;

use App\Enums\MunicipalityVariableType;
use App\Observers\MunicipalityVariableObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(MunicipalityVariableObserver::class)]
class MunicipalityVariable extends Model
{
    /** @use HasFactory<\Database\Factories\MunicipalityVariableFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'municipality_id',
        'name',
        'key',
        'type',
        'value',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => MunicipalityVariableType::class,
            'value' => 'json',
            'is_default' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    protected function formattedValue(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                MunicipalityVariableType::Text => (string) $this->value,
                MunicipalityVariableType::Number => (float) $this->value,
                MunicipalityVariableType::DateRange,
                MunicipalityVariableType::TimeRange,
                MunicipalityVariableType::DateTimeRange => $this->value, // Already JSON
                MunicipalityVariableType::Boolean => (bool) $this->value,
                default => $this->value,
            },
        );
    }

    protected function formattedFilamentTableValue(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                MunicipalityVariableType::Text => (string) $this->value,
                MunicipalityVariableType::Number => (float) $this->value,
                MunicipalityVariableType::DateRange => implode(' - ', array_reverse($this->value)), /** @phpstan-ignore argument.type */
                MunicipalityVariableType::TimeRange => implode(' - ', array_reverse($this->value)), /** @phpstan-ignore argument.type */
                MunicipalityVariableType::DateTimeRange => implode(' - ', array_reverse($this->value)), /** @phpstan-ignore argument.type */
                MunicipalityVariableType::Boolean => $this->value ? __('Ja') : __('Nee'),
                default => $this->value,
            },
        );
    }
}
