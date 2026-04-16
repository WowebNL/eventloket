<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenNotificationRequest;
use App\Jobs\ProcessOpenNotification;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Log;

class OpenNotificationsController extends Controller
{
    public function listen(OpenNotificationRequest $request)
    {
        if (config('services.open_notificaties.log_incoming')) {
            Log::info('Open Notificaties: incoming notification', $request->validated());
        }

        dispatch(new ProcessOpenNotification(new OpenNotification(...$request->validated())));
    }
}
