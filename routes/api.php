<?php

use App\Http\Controllers\Api\OpenNotificationsController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

// Open Forms is verwijderd; de wizard zit nu inline in Filament en
// gebruikt de `app/EventForm/Services/`-classes direct. De drie OF-
// pull-endpoints (locationserver, events, municipality-variables) en
// het report-questions endpoint zijn verwijderd samen met de
// `NormalizeOpenformsInput`-middleware.
// `open-notifications/listen` is een ZGW-webhook en onafhankelijk van OF.
Route::group(['middleware' => [EnsureClientIsResourceOwner::class]], function () {
    Route::post('/open-notifications/listen', [OpenNotificationsController::class, 'listen'])->name('api.open-notifications.listen');
});
