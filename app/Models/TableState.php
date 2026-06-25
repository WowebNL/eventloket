<?php

namespace App\Models;

use Database\Factories\TableStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $state
 */
class TableState extends Model
{
    /** @use HasFactory<TableStateFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'table_key',
        'state',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }
}
