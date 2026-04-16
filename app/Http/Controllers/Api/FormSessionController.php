<?php

namespace App\Http\Controllers\Api;

use App\EventForm\Services\FormSessionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FormSessionRequest;
use App\Models\FormsubmissionSession;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class FormSessionController extends Controller
{
    public function __construct(
        private readonly FormSessionService $service,
    ) {}

    public function __invoke(FormSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $formSubmission = FormsubmissionSession::where('uuid', $data['submission_uuid'])->firstOrFail();

        /** @var User $user */
        $user = $formSubmission->user;
        /** @var Organisation $organisation */
        $organisation = $formSubmission->organisation;

        return response()->json([
            'message' => 'Valid session',
            'data' => $this->service->buildFor($user, $organisation),
        ]);
    }
}
