<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultAdviceQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'advisory_id',
        'risico_classificatie',
        'title',
        'description',
        'response_deadline_days',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }
}
