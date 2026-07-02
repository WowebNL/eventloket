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

test('definitief and missing status count as final', function (?string $status) {
    expect(informatieobjectWithStatus($status)->isDefinitief())->toBeTrue();
})->with([
    'definitief' => 'definitief',
    'null' => null,
    'empty' => '',
]);

test('draft statuses are not final', function (string $status) {
    expect(informatieobjectWithStatus($status)->isDefinitief())->toBeFalse();
})->with([
    'in_bewerking' => 'in_bewerking',
    'ter_vaststelling' => 'ter_vaststelling',
    'concept' => 'concept',
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
