<?php

use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('displayName falls back to the zaken url when the name is empty', function () {
    $named = MunicipalityZgwConnection::factory()->create(['name' => 'Heerlen ZGW']);
    $unnamed = MunicipalityZgwConnection::factory()->create([
        'name' => null,
        'zaken_url' => 'https://heerlen.example/zaken/api/v1/',
    ]);

    expect($named->displayName)->toBe('Heerlen ZGW')
        ->and($unnamed->displayName)->toBe('https://heerlen.example/zaken/api/v1/');
});

test('connectionLabel resolves the main and per-municipality connections', function () {
    $connection = MunicipalityZgwConnection::factory()->create(['name' => 'Heerlen ZGW']);

    expect(ZgwRequestLog::connectionLabel('main'))->toBe(__('admin/resources/zgw_request_log.connections.main'))
        ->and(ZgwRequestLog::connectionLabel("gemeente_{$connection->municipality_id}"))->toBe('Heerlen ZGW')
        ->and(ZgwRequestLog::connectionLabel('gemeente_999999'))->toBe('gemeente_999999');
});
