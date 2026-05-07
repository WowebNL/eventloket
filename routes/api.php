<?php

use App\Http\Controllers\Api\OpenNotificationsController;
use App\Http\Controllers\Api\ReportQuestionController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

// Open Forms is verwijderd; de wizard zit nu inline in Filament en
// gebruikt de `app/EventForm/Services/`-classes direct. De drie OF-
// pull-endpoints (locationserver, events, municipality-variables)
// zijn in een eerdere stap weggehaald, evenals de
// `NormalizeOpenformsInput`-middleware (de OF-specifieke header-quirk
// op request-bodies bestond alleen voor die endpoints).
// `report-questions` blijft tot we zeker weten of er nog externe
// consumers zijn; `open-notifications/listen` is een ZGW-webhook en
// onafhankelijk van OF.
Route::group(['middleware' => [EnsureClientIsResourceOwner::class]], function () {
    Route::post('/open-notifications/listen', [OpenNotificationsController::class, 'listen'])->name('api.open-notifications.listen');
    Route::get('/report-questions/{municipality:brk_identification}', ReportQuestionController::class)->name('api.report-questions');
});
