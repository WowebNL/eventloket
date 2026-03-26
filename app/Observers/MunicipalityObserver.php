<?php

namespace App\Observers;

use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\ReportQuestion;

class MunicipalityObserver
{
    /**
     * Handle the Municipality "created" event.
     */
    public function created(Municipality $municipality): void
    {
        // Seed default MunicipalityVariables
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

        // Seed 10 default ReportQuestions
        $defaultQuestions = ReportQuestion::defaultQuestions();

        foreach ($defaultQuestions as $order => $question) {
            ReportQuestion::create([
                'municipality_id' => $municipality->id,
                'order' => $order,
                'question' => $question,
                'is_active' => true,
            ]);
        }
    }
}
