<?php

namespace App\Models;

use Database\Factories\StatusResultaatColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusResultaatColor extends Model
{
    /** @use HasFactory<StatusResultaatColorFactory> */
    use HasFactory;

    protected $fillable = [
        'status_name',
        'resultaat',
        'color',
    ];

    /**
     * Resolve the configured color for the given status/resultaat combination.
     */
    public static function colorFor(string $statusName, ?string $resultaat = null): ?string
    {
        return self::query()
            ->where('status_name', $statusName)
            ->where('resultaat', $resultaat)
            ->value('color');
    }
}
