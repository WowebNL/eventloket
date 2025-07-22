<?php

use App\Filament\Advisor\Pages\AcceptAdvisoryInvite;
use App\Filament\Organiser\Pages\AcceptOrganisationInvite;
use App\Filament\Pages\AcceptReviewerInvite;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('admin/reviewer-invites/{token}', AcceptReviewerInvite::class)
    ->name('reviewer-invites.accept');

Route::middleware('signed')
    ->get('advisory/advisory-invites/{token}', AcceptAdvisoryInvite::class)
    ->name('advisory-invites.accept');

Route::middleware('signed')
    ->get('organiser/organisation-invites/{token}', AcceptOrganisationInvite::class)
    ->name('organisation-invites.accept');
