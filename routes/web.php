<?php

use App\Filament\Public\Pages\AcceptAdminInvite;
use App\Filament\Public\Pages\AcceptAdvisoryInvite;
use App\Filament\Public\Pages\AcceptMunicipalityInvite;
use App\Filament\Public\Pages\AcceptOrganisationInvite;
use App\Settings\WelcomeSettings;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('admin/admin-invites/{token}', AcceptAdminInvite::class)
    ->name('admin-invites.accept');

Route::middleware('signed')
    ->get('municipality/municipality-invites/{token}', AcceptMunicipalityInvite::class)
    ->name('municipality-invites.accept');

Route::middleware('signed')
    ->get('advisory/advisory-invites/{token}', AcceptAdvisoryInvite::class)
    ->name('advisory-invites.accept');

Route::middleware('signed')
    ->get('organiser/organisation-invites/{token}', AcceptOrganisationInvite::class)
    ->name('organisation-invites.accept');

// Route::get('/', Welcome::class)->name('welcome');
Route::get('/', fn (WelcomeSettings $settings) => view('welcome')->with($settings->toArray()))->name('welcome');
