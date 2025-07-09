<?php

use App\Filament\Organiser\Pages\AcceptOrganisationInvite;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('organiser/organisation-invites/{token}', AcceptOrganisationInvite::class)
    ->name('organisation-invites.accept');
