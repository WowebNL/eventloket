<?php

use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\ZgwManager;

it('registers the zgw package', function () {
    expect(class_exists(Zgw::class))->toBeTrue()
        ->and(app()->bound(ZgwManager::class))->toBeTrue();
});

it('defines a main connection targeting ZGW 1.5 by default', function () {
    expect(config('zgw.default'))->toBe('main')
        ->and(config('zgw.connections.main.version'))->toBe('1.5');
});

it('derives the per-API base urls from OPENZAAK_URL', function () {
    $base = rtrim((string) env('OPENZAAK_URL', ''), '/');
    $urls = config('zgw.connections.main.urls');

    expect($urls['zaken'])->toBe($base.'/zaken/api/v1/')
        ->and($urls['catalogi'])->toBe($base.'/catalogi/api/v1/')
        ->and($urls['documenten'])->toBe($base.'/documenten/api/v1/')
        ->and($urls['besluiten'])->toBe($base.'/besluiten/api/v1/')
        ->and($urls['autorisaties'])->toBe($base.'/autorisaties/api/v1/');
});

it('carries the application-level technical params with OpenZaak defaults', function () {
    $main = config('zgw.connections.main');

    expect($main['bronorganisatie_rsin'])->toBe('820151130')
        ->and($main['geometry_format'])->toBe('json')
        ->and($main)->toHaveKey('eigenschap_date_format');
});
