<?php

use App\EventForm\Persistence\Draft;
use App\Http\Controllers\DocumentController;
use App\Livewire\AcceptInvites\AcceptAdminInvite;
use App\Livewire\AcceptInvites\AcceptAdvisoryInvite;
use App\Livewire\AcceptInvites\AcceptMunicipalityInvite;
use App\Livewire\AcceptInvites\AcceptOrganisationInvite;
use App\Settings\WelcomeSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
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

// auth in DocumentRequest
Route::get('/zaak-documents/{zaak}/{documentuuid}/{type?}', DocumentController::class)->name('zaak.documents.view');

// Test-only endpoint: leeg de Draft van een specifieke organiser-user.
// Alleen actief in local/testing zodat productie er niets van merkt.
// Wordt aangeroepen door Playwright-scenarios die via Docker draaien
// en daarom geen `./vendor/bin/sail` kunnen aanroepen voor de cleanup.
if (app()->environment(['local', 'testing'])) {
    Route::post('/_test/reset-draft', function (Request $request) {
        $email = (string) $request->input('email', '');
        if ($email === '') {
            return response()->json(['ok' => false, 'reason' => 'email required'], 422);
        }
        $deleted = Draft::query()
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->delete();

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    })->name('test.reset-draft')->withoutMiddleware([PreventRequestForgery::class]);
}
