<?php

use App\Http\Controllers\Api\OpenNotificationsController;
use App\Http\Middleware\LogOpenNotificationRejection;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

// Open Forms has been removed; the wizard now lives inline in Filament and
// uses the `app/EventForm/Services/` classes directly. The three OF pull
// endpoints (locationserver, events, municipality-variables) and the
// report-questions endpoint were removed together with the
// `NormalizeOpenformsInput` middleware.
// `open-notifications/listen` is a ZGW webhook and independent of OF.
Route::post('/open-notifications/listen', [OpenNotificationsController::class, 'listen'])
    ->middleware([
        // Runs first, ahead of anything that touches the database. The limit
        // and the reasoning behind it live with the named limiter in
        // AppServiceProvider.
        'throttle:open-notifications',
        // Runs ahead of the scope check so it can observe (and log) auth, scope
        // and host-rule rejections raised by the middleware and validation that
        // follow.
        LogOpenNotificationRejection::class,
        // The scope this route demands is the same constant the webhook token is
        // minted with, so the requirement and the credential cannot drift apart:
        // raising the bar here without raising it there would turn every incoming
        // notification into a refusal.
        EnsureClientIsResourceOwner::class.':'.WebhookTokenIssuer::SCOPE,
    ])
    ->name('api.open-notifications.listen');
