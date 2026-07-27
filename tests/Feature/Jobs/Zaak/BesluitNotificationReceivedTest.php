<?php

declare(strict_types=1);

use App\Jobs\Zaak\BesluitNotificationReceived;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\OpenNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

function besluitNotification(string $besluitUrl): OpenNotification
{
    return new OpenNotification(
        actie: 'create',
        kanaal: 'besluiten',
        resource: 'besluit',
        hoofdObject: $besluitUrl,
        resourceUrl: $besluitUrl,
        aanmaakdatum: now()->toIso8601String(),
    );
}

test('clears the cached besluiten and documenten of the zaak the besluit belongs to', function () {
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1';
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';

    Http::fake([
        $besluitUrl => Http::response(['url' => $besluitUrl, 'zaak' => $zaakUrl], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);

    Cache::put("zaak.{$zaak->id}.besluiten", collect(['stale']));
    Cache::put("zaak.{$zaak->id}.documenten", collect(['stale']));

    (new BesluitNotificationReceived(besluitNotification($besluitUrl)))->handle();

    expect(Cache::has("zaak.{$zaak->id}.besluiten"))->toBeFalse()
        ->and(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();
});

test('does nothing when the besluit belongs to a zaak we do not know', function () {
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';

    Http::fake([
        $besluitUrl => Http::response([
            'url' => $besluitUrl,
            'zaak' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/onbekend',
        ], 200),
    ]);

    (new BesluitNotificationReceived(besluitNotification($besluitUrl)))->handle();
})->throwsNoExceptions();

test('a besluit that cannot be read is logged and does not fail the job', function () {
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';

    Http::fake([
        $besluitUrl => Http::response(['detail' => 'Not found'], 404),
    ]);

    (new BesluitNotificationReceived(besluitNotification($besluitUrl)))->handle();
})->throwsNoExceptions();
