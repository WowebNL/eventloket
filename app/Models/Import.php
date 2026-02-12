<?php

namespace App\Models;

use Filament\Actions\Imports\Models\Import as BaseImport;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends BaseImport
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
