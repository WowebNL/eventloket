<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ValidateEventloketTokenRequest;
use App\Http\Resources\Api\EventloketTokenResource;
use App\Services\EventloketTokenService;

class ValidateEventloketTokenController extends Controller
{
    public function __invoke(
        ValidateEventloketTokenRequest $request,
        EventloketTokenService $tokenService,
    ): EventloketTokenResource {
        $result = $tokenService->validate($request->validated('token'));

        return new EventloketTokenResource($result);
    }
}
