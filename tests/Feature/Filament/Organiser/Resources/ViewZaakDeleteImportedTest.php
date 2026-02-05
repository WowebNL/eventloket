<?php

use App\Models\Zaak;
use App\Models\Zaaktype;

beforeEach(function () {
    $this->zaaktype = Zaaktype::factory()->create();
});

test('imported zaak can be identified for deletion', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($zaak->is_imported)->toBeTrue();
});

test('non-imported zaak should not be marked for deletion', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => 'https://example.com/zaak/1',
        'imported_data' => null,
    ]);

    expect($zaak->is_imported)->toBeFalse();
});

test('imported zaak can be deleted from database', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    $zaakId = $zaak->id;
    $zaak->delete();

    expect(Zaak::find($zaakId))->toBeNull();
});
