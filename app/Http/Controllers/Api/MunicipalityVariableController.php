<?php

namespace App\Http\Controllers\Api;

use App\Enums\MunicipalityVariableType;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\Request;

class MunicipalityVariableController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Municipality $municipality)
    {
        $asKeyValue = $request->boolean('as_key_value', false);

        $variables = $municipality
            ->variables()
            ->withTrashed()
            ->get();

        // Filter out ReportQuestion types if municipality uses new system
        if ($municipality->use_new_report_questions) {
            $variables = $variables->filter(function ($variable) {
                return $variable->type !== MunicipalityVariableType::ReportQuestion;
            });
        }

        $variables = $variables->map(function ($variable) {
            return [
                'id' => $variable->id,
                'name' => $variable->name,
                'key' => $variable->key,
                'type' => $variable->type,
                'value' => $variable->formatted_value,
                'is_default' => $variable->is_default,
            ];
        });

        return response()->json([
            'data' => $asKeyValue ? $variables->pluck('value', 'key') : $variables->values(),
        ]);
    }
}
