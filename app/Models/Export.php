<?php

namespace App\Models;

use Filament\Actions\Exports\Models\Export as BaseExport;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends BaseExport
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
