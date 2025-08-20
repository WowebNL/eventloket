<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationServerCheckRequest;
use Illuminate\Support\Facades\Log;

class LocationServerController extends Controller
{
    public function check(LocationServerCheckRequest $request)
    {
        Log::info(json_encode($request->all(), JSON_PRETTY_PRINT));

        // $data = $request->validated();

        // Process the validated data
    }
}
