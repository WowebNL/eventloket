<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Jobs\ZaaktypeNotificationReceived;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Zaaktype;
use App\Notifications\ZaaktypeKoppelingWarning;
use App\Services\Zgw\ZaaktypeRefresher;
use App\ValueObjects\OpenNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

const ZTN_OWN_BASE = 'https://gemeente.example.com';

beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

function zaaktypeNotification(string $hoofdObject, string $actie = 'partial_update'): OpenNotification
{
    return new OpenNotification(
        actie: $actie,
        kanaal: 'zaaktypen',
        resource: 'zaaktype',
        hoofdObject: $hoofdObject,
        resourceUrl: $hoofdObject,
        aanmaakdatum: now(),
    );
}

/**
 * A municipality with an active own ZGW connection (host gemeente.example.com,
 * unique, so incoming urls attribute to it), a Vergunning koppeling for OWN-1
 * and the matching local own-instance zaaktype row. The mapping is created
 * without events: the observer-triggered refresh would need HTTP fakes that
 * shadow the per-test fakes (earlier-registered stubs win on equal patterns).
 *
 * @return array{0: Municipality, 1: Zaaktype}
 */
function ownInstanceSetup(string $versionUrl, string $municipalityName = 'Heerlen'): array
{
    $municipality = Municipality::factory()->create(['name' => $municipalityName]);
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $municipality->id]);

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]));

    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$municipality->id}",
        'name' => 'Eigen evenementenvergunning',
        'role' => ZaaktypeRole::Vergunning->value,
        'is_active' => true,
        'zgw_zaaktype_url' => $versionUrl,
    ]);
    $zaaktype->municipality_id = $municipality->id;
    $zaaktype->save();

    return [$municipality, $zaaktype];
}

/**
 * @return array<string, mixed>
 */
function zaaktypeVersionData(string $url, string $identificatie = 'OWN-1', string $omschrijving = 'Eigen evenementenvergunning'): array
{
    return [
        'url' => $url,
        'identificatie' => $identificatie,
        'omschrijving' => $omschrijving,
        'concept' => false,
        'beginGeldigheid' => '2026-01-01',
        'versiedatum' => '2026-01-01',
    ];
}

/**
 * Healthy child lists for the blueprint health check: eindstatus, initiator
 * roltype, Ingetrokken resultaattype, intern_zaaknummer eigenschap and an
 * inline (RX-style) informatieobjecttype.
 *
 * @return array<string, mixed>
 */
function healthyCatalogusFakes(array $overrides = []): array
{
    return array_merge([
        ZTN_OWN_BASE.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
            ['omschrijving' => 'Afgehandeld', 'volgnummer' => 2, 'isEindstatus' => true],
        ]), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/resultaattypen*' => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Verleend', 'omschrijvingGeneriek' => 'Afgehandeld'],
            ['omschrijving' => 'Ingetrokken', 'omschrijvingGeneriek' => 'Ingetrokken'],
        ]), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([
            ['naam' => 'intern_zaaknummer'],
        ]), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => 'Bijlage'],
        ]), 200),
    ], $overrides);
}

test('a published new version refreshes the own-instance row and clears the version caches', function () {
    $v1 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v1';
    $v2 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v2';

    [$municipality, $zaaktype] = ownInstanceSetup($v1);

    Http::fake(array_merge([
        $v2 => Http::response(zaaktypeVersionData($v2), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([zaaktypeVersionData($v2)]), 200),
    ], healthyCatalogusFakes()));

    Cache::put("statustypen.v2.gemeente_{$municipality->id}", ['stale']);
    Cache::put("zaak_status_name_options_{$municipality->id}", ['stale']);

    (new ZaaktypeNotificationReceived(zaaktypeNotification($v2)))->handle(app(ZaaktypeRefresher::class));

    expect($zaaktype->refresh())
        ->zgw_zaaktype_url->toBe($v2)
        ->is_active->toBeTrue()
        ->and(Cache::has("statustypen.v2.gemeente_{$municipality->id}"))->toBeFalse()
        ->and(Cache::has("zaak_status_name_options_{$municipality->id}"))->toBeFalse();

    Notification::assertNothingSent();
});

test('a mapped zaaktype without a valid version engages the main fallback and warns once', function () {
    $v1 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v1';

    [$municipality, $zaaktype] = ownInstanceSetup($v1);

    $mainZaaktype = Zaaktype::factory()->create([
        'identificatie' => 'MAIN-1',
        'connection' => 'main',
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'role' => ZaaktypeRole::Vergunning->value,
        'is_active' => true,
    ]);

    $admin = User::factory()->create(['role' => Role::Admin]);
    $beheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $beheerder->municipalities()->attach($municipality);

    // The destroyed version 404s; the identificatie is matched locally by url.
    Http::fake([
        $v1 => Http::response([], 404),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    $job = new ZaaktypeNotificationReceived(zaaktypeNotification($v1, 'destroy'));
    $job->handle(app(ZaaktypeRefresher::class));

    expect($zaaktype->refresh()->is_active)->toBeFalse()
        ->and($mainZaaktype->refresh()->municipality_id)->toBe($municipality->id);

    Notification::assertSentTo(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class);
    Notification::assertSentTo(MunicipalityUser::firstWhere('id', $beheerder->id), ZaaktypeKoppelingWarning::class);

    // A second notification for the same (still broken) zaaktype stays silent:
    // the warning fires on the transition only.
    $job->handle(app(ZaaktypeRefresher::class));

    Notification::assertSentToTimes(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class, 1);
});

test('a missing main fallback candidate still warns and deactivates the own row', function () {
    $v1 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v1';

    [, $zaaktype] = ownInstanceSetup($v1);
    $admin = User::factory()->create(['role' => Role::Admin]);

    Http::fake([
        $v1 => Http::response([], 404),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    (new ZaaktypeNotificationReceived(zaaktypeNotification($v1, 'destroy')))->handle(app(ZaaktypeRefresher::class));

    expect($zaaktype->refresh()->is_active)->toBeFalse()
        ->and(Zaaktype::where('connection', 'main')->whereNotNull('municipality_id')->exists())->toBeFalse();

    Notification::assertSentTo(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class);
});

test('a valid version again reactivates the own row, keeps the fallback link and notifies the restore', function () {
    $v2 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v2';

    [$municipality, $zaaktype] = ownInstanceSetup($v2);
    $zaaktype->update(['is_active' => false]);

    // The fallback main row linked during the outage.
    $mainZaaktype = Zaaktype::factory()->create([
        'identificatie' => 'MAIN-1',
        'connection' => 'main',
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'role' => ZaaktypeRole::Vergunning->value,
        'is_active' => true,
    ]);
    $mainZaaktype->municipality_id = $municipality->id;
    $mainZaaktype->save();

    $admin = User::factory()->create(['role' => Role::Admin]);

    Http::fake(array_merge([
        $v2 => Http::response(zaaktypeVersionData($v2), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([zaaktypeVersionData($v2)]), 200),
    ], healthyCatalogusFakes()));

    (new ZaaktypeNotificationReceived(zaaktypeNotification($v2)))->handle(app(ZaaktypeRefresher::class));

    // The own row wins again through the resolve ordering; the main link stays
    // so zaken created during the fallback keep their municipality.
    expect($zaaktype->refresh()->is_active)->toBeTrue()
        ->and($mainZaaktype->refresh()->municipality_id)->toBe($municipality->id);

    Notification::assertSentTo(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class);
});

test('a new version missing an eindstatus warns without engaging the fallback', function () {
    $v1 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v1';
    $v2 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v2';

    [, $zaaktype] = ownInstanceSetup($v1);
    $admin = User::factory()->create(['role' => Role::Admin]);

    Http::fake(array_merge([
        $v2 => Http::response(zaaktypeVersionData($v2), 200),
        ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([zaaktypeVersionData($v2)]), 200),
    ], healthyCatalogusFakes([
        ZTN_OWN_BASE.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
        ]), 200),
    ])));

    (new ZaaktypeNotificationReceived(zaaktypeNotification($v2)))->handle(app(ZaaktypeRefresher::class));

    expect($zaaktype->refresh())
        ->is_active->toBeTrue()
        ->zgw_zaaktype_url->toBe($v2)
        ->and(Zaaktype::where('connection', 'main')->whereNotNull('municipality_id')->exists())->toBeFalse();

    Notification::assertSentTo(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class);
});

test('an unmapped own-instance identificatie is ignored', function () {
    $v1 = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v1';
    $other = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/other';

    [, $zaaktype] = ownInstanceSetup($v1);

    Http::fake([
        $other => Http::response(zaaktypeVersionData($other, 'UNMAPPED'), 200),
    ]);

    (new ZaaktypeNotificationReceived(zaaktypeNotification($other)))->handle(app(ZaaktypeRefresher::class));

    expect($zaaktype->refresh())
        ->is_active->toBeTrue()
        ->zgw_zaaktype_url->toBe($v1);

    Notification::assertNothingSent();
});

test('a destroy without any local url match is ignored', function () {
    $unknown = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/unknown';

    User::factory()->create(['role' => Role::Admin]);

    Http::fake([
        $unknown => Http::response([], 404),
    ]);

    (new ZaaktypeNotificationReceived(zaaktypeNotification($unknown, 'destroy')))->handle(app(ZaaktypeRefresher::class));

    Notification::assertNothingSent();
});

test('a main zaaktype without a valid version is deactivated, unlinked and warned about', function () {
    $mainUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/main-1';

    $municipality = Municipality::factory()->create();
    $mainZaaktype = Zaaktype::factory()->create([
        'identificatie' => 'MAIN-1',
        'connection' => 'main',
        'name' => 'Evenementenvergunning gemeente '.$municipality->name,
        'role' => ZaaktypeRole::Vergunning->value,
        'is_active' => true,
        'zgw_zaaktype_url' => $mainUrl,
    ]);
    $mainZaaktype->municipality_id = $municipality->id;
    $mainZaaktype->save();

    $admin = User::factory()->create(['role' => Role::Admin]);

    Http::fake([
        $mainUrl => Http::response([], 404),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    (new ZaaktypeNotificationReceived(zaaktypeNotification($mainUrl, 'destroy')))->handle(app(ZaaktypeRefresher::class));

    expect($mainZaaktype->refresh())
        ->is_active->toBeFalse()
        ->municipality_id->toBeNull();

    Notification::assertSentTo(AdminUser::firstWhere('id', $admin->id), ZaaktypeKoppelingWarning::class);
});

test('notifications for the same version url collapse into one unique job', function () {
    Queue::fake([ZaaktypeNotificationReceived::class]);

    $url = ZTN_OWN_BASE.'/catalogi/api/v1/zaaktypen/v2';

    ZaaktypeNotificationReceived::dispatch(zaaktypeNotification($url));
    ZaaktypeNotificationReceived::dispatch(zaaktypeNotification($url, 'create'));

    Queue::assertPushed(ZaaktypeNotificationReceived::class, 1);
});
