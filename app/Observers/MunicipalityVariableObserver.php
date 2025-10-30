<?php

namespace App\Observers;

use App\Models\Municipality;
use App\Models\MunicipalityVariable;

class MunicipalityVariableObserver
{
    /**
     * Handle the MunicipalityVariable "created" event.
     */
    public function created(MunicipalityVariable $municipalityVariable): void
    {
        if ($municipalityVariable->municipality_id === null) {
            // An admin has created a default variable. Add this variable to all municipalities.
            foreach (Municipality::all() as $municipality) {
                MunicipalityVariable::create([
                    'municipality_id' => $municipality->id,
                    'name' => $municipalityVariable->name,
                    'key' => $municipalityVariable->key,
                    'type' => $municipalityVariable->type,
                    'value' => $municipalityVariable->value,
                    'is_default' => true,
                ]);
            }
        }
    }

    public function deleted(MunicipalityVariable $municipalityVariable)
    {
        if ($municipalityVariable->municipality_id === null) {
            // An admin has deleted a default variable. Delete this variable from all municipalities.
            MunicipalityVariable::where('key', $municipalityVariable->key)->delete();
        }
    }
}
