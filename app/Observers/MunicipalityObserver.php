<?php

namespace App\Observers;

use App\Models\Municipality;
use App\Models\MunicipalityVariable;

class MunicipalityObserver
{
    /**
     * Handle the Municipality "created" event.
     */
    public function created(Municipality $municipality): void
    {
        foreach (MunicipalityVariable::where('municipality_id', null)->get() as $defaultVariable) {
            MunicipalityVariable::create([
                'municipality_id' => $municipality->id,
                'name' => $defaultVariable->name,
                'key' => $defaultVariable->key,
                'type' => $defaultVariable->type,
                'value' => $defaultVariable->value,
                'is_default' => true,
            ]);
        }
    }
}
