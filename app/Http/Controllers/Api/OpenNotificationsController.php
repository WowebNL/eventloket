<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenNotificationRequest;
use App\Jobs\ProcessOpenNotification;
use App\ValueObjects\OpenNotification;

class OpenNotificationsController extends Controller
{
    public function listen(OpenNotificationRequest $request)
    {
        dispatch(new ProcessOpenNotification(new OpenNotification(...$request->validated())));
    }
}
