<?php

namespace App\Models;

use Database\Factories\ReportQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportQuestion extends Model
{
    /** @use HasFactory<ReportQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'order',
        'question',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public static function defaultQuestions(): array
    {
        return config('report-questions.defaults', []);
    }
}
