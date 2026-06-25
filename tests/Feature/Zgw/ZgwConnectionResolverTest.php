<?php

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;

beforeEach(function () {
    $this->resolver = app(ZgwConnectionResolver::class);
});

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
