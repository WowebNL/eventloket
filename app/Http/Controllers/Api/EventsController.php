<?php

namespace App\Http\Controllers\Api;

use App\EventForm\Services\EventsCheckService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckEventsRequest;
use Illuminate\Http\JsonResponse;

class EventsController extends Controller
{
    public function __construct(
        private readonly EventsCheckService $service,
    ) {}

    public function check(CheckEventsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->service->check(
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            municipalityBrkId: $data['municipality'],
        );

        return response()->json($result);
    }
}
