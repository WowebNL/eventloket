<?php

namespace App\Http\Controllers\Api;

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
        $variables = $municipality
            ->variables()
            ->withTrashed()
            ->get()
            ->map(function ($variable) {
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
            'data' => $variables,
        ]);
    }
}
