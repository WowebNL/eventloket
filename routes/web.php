<?php

use App\Filament\Advisor\Pages\AcceptAdvisoryInvite;
use App\Filament\Organiser\Pages\AcceptOrganisationInvite;
use App\Filament\Pages\AcceptAdminInvite;
use App\Filament\Pages\Welcome;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('admin/admin-invites/{token}', AcceptAdminInvite::class)
    ->name('admin-invites.accept');

Route::middleware('signed')
    ->get('advisory/advisory-invites/{token}', AcceptAdvisoryInvite::class)
    ->name('advisory-invites.accept');

Route::middleware('signed')
    ->get('organiser/organisation-invites/{token}', AcceptOrganisationInvite::class)
    ->name('organisation-invites.accept');

// Route::get('/', Welcome::class)->name('welcome');
Route::get('/', fn() => view('welcome'))->name('welcome');