<?php

use App\EventForm\Persistence\Draft;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentZipController;
use App\Livewire\AcceptInvites\AcceptAdminInvite;
use App\Livewire\AcceptInvites\AcceptAdvisoryInvite;
use App\Livewire\AcceptInvites\AcceptMunicipalityInvite;
use App\Livewire\AcceptInvites\AcceptOrganisationInvite;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
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

// auth checked in controller via cache token — must be registered before the generic document route
Route::middleware('auth')->get('/zaak-documents/{zaak}/zip/{token}', DocumentZipController::class)->name('zaak.documents.zip');

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

    // Test-only: maak een Zaak met een form_state_snapshot zoals de
    // backfill-command die produceert (legacy-gemapte velden + kaart als
    // geojson), zodat een Playwright-scenario de "herhaal aanvraag"-prefill
    // in de browser kan verifiëren. Geeft het zaak-id terug.
    Route::post('/_test/seed-prefill-zaak', function (Request $request) {
        $email = (string) $request->input('email', '');
        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json(['ok' => false, 'reason' => 'user not found'], 422);
        }
        $organisation = $user->organisations()->first();
        if (! $organisation) {
            return response()->json(['ok' => false, 'reason' => 'no organisation'], 422);
        }
        $zaaktype = Zaaktype::query()->first();

        // Realistische snapshot zoals de backfill die produceert: genoeg om
        // door de wizard te navigeren (stap 1 + 2 gevuld) plus een buiten-
        // locatie met een getekende polygon, zodat een Playwright-scenario
        // kan verifiëren of de prefill óók de kaart-tekening rendert.
        $zaak = Zaak::factory()->create([
            'organisation_id' => $organisation->id,
            'organiser_user_id' => $user->id,
            'zaaktype_id' => $zaaktype?->id,
            'form_state_snapshot' => ['values' => [
                // Stap 1 — Contactgegevens
                'watIsUwVoornaam' => 'PrefillEva',
                'watIsUwAchternaam' => 'PrefillTest',
                'postcode1' => '6411CD',
                'huisnummer1' => '1',
                'straatnaam1' => 'Marktplein',
                'plaatsnaam1' => 'Heerlen',
                // Stap 2 — Het evenement
                'watIsDeNaamVanHetEvenementVergunning' => 'Hergebruikte Aanvraag',
                'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Prefill-omschrijving.',
                'soortEvenement' => 'Sportevenement',
                // Stap 3 — Locatie: buiten, met een getekende polygon
                'waarVindtHetEvenementPlaats' => ['buiten'],
                'naamVanDeLocatieKaart' => 'Festivalweide',
                'locatieSOpKaart' => ['geojson' => [
                    'type' => 'FeatureCollection',
                    'features' => [[
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[5.84, 50.90], [5.80, 50.89], [5.86, 50.87], [5.84, 50.90]]]],
                    ]],
                ]],
            ]],
        ]);

        return response()->json(['ok' => true, 'zaak_id' => $zaak->id]);
    })->name('test.seed-prefill-zaak')->withoutMiddleware([PreventRequestForgery::class]);
}
