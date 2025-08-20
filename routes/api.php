<?php

use App\Http\Controllers\Api\LocationServerController;
use App\Http\Middleware\Api\NormalizeOpenformsInput;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

Route::group(['middleware' => [EnsureClientIsResourceOwner::class, NormalizeOpenformsInput::class]], function () {
    Route::post('/locationserver/check', [LocationServerController::class, 'check'])->name('api.locationserver.check');
});
