<?php

use App\Models\Zaak;
use App\Models\Zaaktype;

beforeEach(function () {
    $this->zaaktype = Zaaktype::factory()->create();
});

test('is_imported returns true when zgw_zaak_url is empty and imported_data is not null', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($zaak->is_imported)->toBeTrue();
});

test('is_imported returns false when zgw_zaak_url is not empty', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => 'https://example.com/zaak/1',
        'imported_data' => ['some' => 'data'],
    ]);

    expect($zaak->is_imported)->toBeFalse();
});

test('is_imported returns false when imported_data is null', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => null,
    ]);

    expect($zaak->is_imported)->toBeFalse();
});

test('is_imported returns false when both zgw_zaak_url is filled and imported_data is not null', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => 'https://example.com/zaak/1',
        'imported_data' => ['some' => 'data'],
    ]);

    expect($zaak->is_imported)->toBeFalse();
});
