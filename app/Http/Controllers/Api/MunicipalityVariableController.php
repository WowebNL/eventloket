<?php

namespace App\Http\Controllers\Api;

use App\EventForm\Services\MunicipalityVariablesService;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipalityVariableController extends Controller
{
    public function __construct(
        private readonly MunicipalityVariablesService $service,
    ) {}

    public function __invoke(Request $request, Municipality $municipality): JsonResponse
    {
        $asKeyValue = $request->boolean('as_key_value', false);

        $data = $asKeyValue
            ? $this->service->forMunicipalityAsKeyValue($municipality)
            : $this->service->forMunicipality($municipality);

        return response()->json(['data' => $data]);
    }
}
