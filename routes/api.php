<?php

use App\Http\Controllers\Api\OpenNotificationsController;
use App\Http\Middleware\LogOpenNotificationRejection;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

// Open Forms is verwijderd; de wizard zit nu inline in Filament en
// gebruikt de `app/EventForm/Services/`-classes direct. De drie OF-
// pull-endpoints (locationserver, events, municipality-variables) en
// het report-questions endpoint zijn verwijderd samen met de
// `NormalizeOpenformsInput`-middleware.
// `open-notifications/listen` is een ZGW-webhook en onafhankelijk van OF.
Route::post('/open-notifications/listen', [OpenNotificationsController::class, 'listen'])
    ->middleware([
        // Runs first so it can observe (and log) auth, scope and host-rule
        // rejections raised by the middleware and validation that follow.
        LogOpenNotificationRejection::class,
        // The scope this route demands is the same constant the webhook token is
        // minted with, so the requirement and the credential cannot drift apart:
        // raising the bar here without raising it there would turn every incoming
        // notification into a refusal.
        EnsureClientIsResourceOwner::class.':'.WebhookTokenIssuer::SCOPE,
    ])
    ->name('api.open-notifications.listen');
