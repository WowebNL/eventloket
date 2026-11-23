<?php

use App\Http\Controllers\Api\OpenNotificationsController;
use App\Http\Middleware\AnswerOpenNotificatiesTestNotification;
use App\Http\Middleware\IdentifyWebhookClient;
use App\Http\Middleware\LogOpenNotificationRejection;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Route;

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
        // Identifies the calling connection from the token and demands the scope
        // the webhook token is minted with. The requirement and the credential
        // share one constant, so they cannot drift apart: raising the bar here
        // without raising it there would turn every incoming notification into
        // a refusal.
        IdentifyWebhookClient::class.':'.WebhookTokenIssuer::SCOPE,
        // Answers the test notification a Notificaties API sends when an
        // abonnement is saved. Only reached by an authenticated caller, and it
        // ends the request before validation and processing.
        AnswerOpenNotificatiesTestNotification::class,
    ])
    ->name('api.open-notifications.listen');
