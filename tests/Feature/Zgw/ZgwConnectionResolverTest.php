<?php

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->resolver = app(ZgwConnectionResolver::class);
});

/**
 * @return array<string, null|array<int, string>>
 */
function singleHostConnection(string $zakenUrl): array
{
    return [
        'zaken_url' => $zakenUrl,
        'catalogi_url' => null,
        'documenten_url' => null,
        'besluiten_url' => null,
        'autorisaties_url' => null,
        'notificaties_url' => null,
        'allowed_hosts' => [],
    ];
}

it('returns the main connection for a null context', function () {
    expect($this->resolver->for(null))->toBe('main')
        ->and($this->resolver->forMunicipality(null))->toBe('main');
});

it('resolves the main connection for a municipality', function () {
    $municipality = Municipality::factory()->create();

    expect($this->resolver->for($municipality))->toBe('main')
        ->and($municipality->zgwConnectionName())->toBe('main');
});

it('resolves a zaaktype through its municipality', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create();

    expect($this->resolver->for($zaaktype))->toBe('main')
        ->and($zaaktype->zgwConnectionName())->toBe('main');
});

it('resolves a zaak through its zaaktype', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create();
    $zaak = Zaak::factory()->for($zaaktype)->create();

    expect($this->resolver->for($zaak))->toBe('main')
        ->and($zaak->zgwConnectionName())->toBe('main');
});

it('maps an incoming zaak url back to a connection, falling back to main', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create();
    Zaak::factory()->for($zaaktype)->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/known',
    ]);

    expect($this->resolver->forUrl('https://zgw.example.com/zaken/api/v1/zaken/known'))->toBe('main')
        ->and($this->resolver->forUrl('https://zgw.example.com/zaken/api/v1/zaken/unknown'))->toBe('main');
});

it('resolves a municipality with its own connection and registers its config', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create([
        'zaken_url' => 'https://gemeente-a.example.com/zaken/api/v1/',
        'catalogi_url' => null,
        'eigenschap_date_format' => 'YmdHis',
    ]);

    $name = $this->resolver->for($municipality);

    expect($name)->toBe("gemeente_{$municipality->id}")
        ->and(config("zgw.connections.{$name}.urls.zaken"))->toBe('https://gemeente-a.example.com/zaken/api/v1/')
        ->and(config("zgw.connections.{$name}.eigenschap_date_format"))->toBe('YmdHis')
        // Unset values are inherited from main.
        ->and(config("zgw.connections.{$name}.secret_rules.min_length"))->toBe(config('zgw.connections.main.secret_rules.min_length'))
        ->and(config("zgw.connections.{$name}.urls.catalogi"))->toBe(config('zgw.connections.main.urls.catalogi'));
});

it('memoises the resolved connection per municipality', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create();

    $first = $this->resolver->for($municipality);

    // Deleting the row afterwards must not change an already-resolved name.
    $municipality->zgwConnection()->delete();

    expect($this->resolver->for($municipality))->toBe($first);
});

it('falls back to main when the connection has a too-short secret', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create([
        'client_secret' => 'too-short',
    ]);

    expect($this->resolver->for($municipality))->toBe('main')
        ->and(config("zgw.connections.gemeente_{$municipality->id}"))->toBeNull();
});

it('maps an incoming url to a municipality connection by its unique host', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create(
        singleHostConnection('https://rx.gemeente-a.example.com/zaken/api/v1/'),
    );

    expect($this->resolver->forUrl('https://rx.gemeente-a.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/1'))
        ->toBe("gemeente_{$municipality->id}");
});

it('falls back to main for a host shared with the main connection', function () {
    $municipality = Municipality::factory()->create();
    // Same host as the main connection (phpunit OPENZAAK_URL): ambiguous.
    MunicipalityZgwConnection::factory()->for($municipality)->create(
        singleHostConnection('https://zgw.example.com/zaken/api/v1/'),
    );

    expect($this->resolver->forUrl('https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/1'))
        ->toBe('main');
});

it('falls back to main for an unknown host', function () {
    expect($this->resolver->forUrl('https://unknown.example.com/zaken/api/v1/zaken/1'))->toBe('main');
});

it('rebuilds the host index when a connection changes', function () {
    $url = 'https://rx.gemeente-b.example.com/zaken/api/v1/zaken/1';

    expect($this->resolver->forUrl($url))->toBe('main');

    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create(
        singleHostConnection('https://rx.gemeente-b.example.com/zaken/api/v1/'),
    );

    expect($this->resolver->forUrl($url))->toBe("gemeente_{$municipality->id}");
});
