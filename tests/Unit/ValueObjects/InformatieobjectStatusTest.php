<?php

declare(strict_types=1);

use App\ValueObjects\ZGW\Informatieobject;

function informatieobjectWithStatus(?string $status): Informatieobject
{
    return new Informatieobject(
        uuid: '1',
        url: 'https://example.com/doc/1',
        creatiedatum: '2026-01-01',
        titel: 'Doc',
        vertrouwelijkheidaanduiding: 'openbaar',
        auteur: 'Test',
        versie: 1,
        bestandsnaam: 'doc.pdf',
        inhoud: '',
        beschrijving: '',
        informatieobjecttype: 'https://example.com/iot/1',
        formaat: 'application/pdf',
        locked: false,
        status: $status,
    );
}

test('finalised and status-less documents count as final', function (?string $status) {
    expect(informatieobjectWithStatus($status)->isDefinitief())->toBeTrue();
})->with([
    'definitief' => 'definitief',
    'gearchiveerd' => 'gearchiveerd',
    'null (own upload)' => null,
    'empty (own upload)' => '',
]);

test('draft and unknown statuses are not final (strict allowlist)', function (string $status) {
    expect(informatieobjectWithStatus($status)->isDefinitief())->toBeFalse();
})->with([
    'in_bewerking' => 'in_bewerking',
    'ter_vaststelling' => 'ter_vaststelling',
    'concept' => 'concept',
    // Anything outside the allowlist is hidden, not just the known drafts.
    'unknown status' => 'iets_onbekends',
]);

test('a null beschrijving is accepted (some ZGW backends omit it)', function () {
    // RX Mission returns beschrijving = null where OpenZaak returns an empty
    // string; constructing the document must not throw a TypeError.
    $document = new Informatieobject(...[
        'uuid' => '1',
        'url' => 'https://example.com/doc/1',
        'creatiedatum' => '2026-01-01',
        'titel' => 'Doc',
        'vertrouwelijkheidaanduiding' => 'openbaar',
        'auteur' => 'Test',
        'versie' => 1,
        'bestandsnaam' => 'doc.pdf',
        'inhoud' => '',
        'beschrijving' => null,
        'informatieobjecttype' => 'https://example.com/iot/1',
        'formaat' => 'application/pdf',
        'locked' => false,
    ]);

    expect($document->beschrijving)->toBeNull();
    expect($document->toArray()['beschrijving'])->toBeNull();
});
