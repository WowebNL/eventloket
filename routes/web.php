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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// service-register version probe. The register signs a "timestamp\npath" message
// with its private ed25519 key; we verify it with the public key in config and
// reject stale timestamps. Every failure returns 404 so the route is invisible to
// anyone without a valid signature. The response exposes only version metadata.
Route::get('/__version', function (Request $request) {
    $publicKey = base64_decode((string) config('register.verify_key'), true);
    $timestamp = $request->header(config('register.timestamp_header'));
    $signature = base64_decode((string) $request->header(config('register.signature_header')), true);

    // Reject anything that is not a well formed key + signature before verifying.
    // base64_decode('', true) returns '' (not false), so a missing or wrong length
    // signature must be caught by the length check: sodium_crypto_sign_verify_detached
    // throws on a signature that is not exactly SODIUM_CRYPTO_SIGN_BYTES, which would
    // turn an unauthenticated request into a 500 and break the "always 404" guarantee.
    if (! $publicKey || ! $timestamp || $signature === false
        || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
        abort(404);
    }

    // Reject stale or future timestamps to stop replay.
    if (abs(time() - (int) $timestamp) > (int) config('register.clock_skew')) {
        abort(404);
    }

    $message = $timestamp."\n".$request->getPathInfo();
    if (! sodium_crypto_sign_verify_detached($signature, $message, $publicKey)) {
        abort(404);
    }

    // Deploy-time facts come from the version file written by
    // `php artisan register:build-version` on deploy (config('register.version_file')).
    // No git and no exec on a web request. A missing file (local dev, or before the
    // first deploy with the command) simply leaves the git fields null.
    $version = [];
    $versionFile = (string) config('register.version_file');
    if (is_file($versionFile)) {
        $decoded = json_decode((string) file_get_contents($versionFile), true);
        if (is_array($decoded)) {
            $version = $decoded;
        }
    }

    $base = base_path();
    $composerLockHash = is_file($base.'/composer.lock')
        ? 'sha256:'.hash_file('sha256', $base.'/composer.lock')
        : null;

    // Extra runtimes for the register's end of life check, keyed by endoflife.date
    // slug: the container OS (read without exec) plus the Node toolchain if the
    // deploy recorded it in the version file.
    $runtimes = [];
    if (! empty($version['nodejs'])) {
        $runtimes['nodejs'] = $version['nodejs'];
    }
    if (is_readable('/etc/os-release')) {
        $osRelease = parse_ini_file('/etc/os-release') ?: [];
        if (! empty($osRelease['ID']) && ! empty($osRelease['VERSION_ID'])) {
            $runtimes[$osRelease['ID']] = $osRelease['VERSION_ID'];
        }
    }

    // The database and cache engines, so the register sees patches that land
    // outside a deploy. These run only after the signature check above, so the
    // register is the only caller (once a day), and they are read only config
    // lookups: no table access, and the response carries bare version numbers, no
    // host, user, schema or extension list. Each engine is wrapped separately so
    // one that is down, absent or not configured drops out of the payload instead
    // of failing the endpoint, because a 500 here would hide the whole version
    // picture behind a single engine outage. Anything not detected is omitted.
    $versionNumber = static function ($raw): ?string {
        return is_string($raw) && preg_match('/\d+(\.\d+)*/', $raw, $m) ? $m[0] : null;
    };

    // PostgreSQL: SHOW server_version returns something like
    // "16.15 (Debian 16.15-1.pgdg120+1)", of which we keep 16.15. Only the default
    // connection is asked, and only when it actually runs on pgsql.
    try {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'pgsql') {
            $row = (array) $connection->selectOne('SHOW server_version');
            $postgres = $versionNumber($row['server_version'] ?? null);
            if ($postgres !== null) {
                $runtimes['postgresql'] = $postgres;
            }
        }
    } catch (Throwable) {
        // No usable database connection: report no engine rather than a 500.
    }

    // Redis or Valkey: INFO server carries the version. Valkey also reports a
    // redis_version, but that is a protocol compatibility number; mapping it onto
    // the redis cycles would produce a wrong end of life verdict, so valkey_version
    // wins and decides which slug is reported. phpredis returns the section flat,
    // predis nests it under "Server".
    try {
        $info = Redis::connection()->info('server');
        $info = is_array($info) ? ($info['Server'] ?? $info) : [];
        $valkey = $versionNumber($info['valkey_version'] ?? null);
        $redis = $versionNumber($info['redis_version'] ?? null);
        if ($valkey !== null) {
            $runtimes['valkey'] = $valkey;
        } elseif ($redis !== null) {
            $runtimes['redis'] = $redis;
        }
    } catch (Throwable) {
        // Same: an unreachable cache yields no redis or valkey runtime.
    }

    return response()->json([
        'php' => PHP_VERSION,
        'framework' => app()->version(),
        'git_tag' => $version['git_tag'] ?? null,
        'git_sha' => $version['git_sha'] ?? null,
        'composer_lock_hash' => $composerLockHash,
        'app_env' => app()->environment(),
        'branch' => $version['branch'] ?? null,
        'runtimes' => $runtimes,
        // When the deploy happened (from the version file) versus when this response
        // was built. For the register, deployed_at is usually the more useful clock.
        'deployed_at' => $version['deployed_at'] ?? null,
        'checked_at' => now()->toIso8601String(),
    ]);
});

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
