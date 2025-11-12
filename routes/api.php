<?php

use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\FormSessionController;
use App\Http\Controllers\Api\LocationServerController;
use App\Http\Controllers\Api\MunicipalityVariableController;
use App\Http\Controllers\Api\OpenNotificationsController;
use App\Http\Middleware\Api\NormalizeOpenformsInput;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

Route::group(['middleware' => [EnsureClientIsResourceOwner::class, NormalizeOpenformsInput::class]], function () {
    Route::post('/locationserver/check', [LocationServerController::class, 'check'])->name('api.locationserver.check');
    Route::post('/events/check', [EventsController::class, 'check'])->name('api.events.check');
    Route::get('/formsessions', FormSessionController::class)->name('api.formsessions.check');
    Route::post('/open-notifications/listen', [OpenNotificationsController::class, 'listen'])->name('api.open-notifications.listen');
    Route::get('/municipality-variables/{municipality:brk_identification}', MunicipalityVariableController::class)->name('api.municipality-variables');
});
