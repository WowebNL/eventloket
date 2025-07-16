<?php

use App\Filament\Organiser\Pages\AcceptOrganisationInvite;
use App\Http\Controllers\OpenFormsController;
use Illuminate\Support\Facades\Route;

Route::get('/form', [OpenFormsController::class, 'form']);

Route::middleware('signed')
    ->get('organiser/organisation-invites/{token}', AcceptOrganisationInvite::class)
    ->name('organisation-invites.accept');
