<?php

declare(strict_types=1);

use App\ValueObjects\ZGW\Besluit;

test('builds from a besluit response that omits the optional toelichting and verzenddatum', function () {
    // A OneGround (RX Mission) besluit omits the optional toelichting,
    // verzenddatum and vervaldatum fields from its response. Building the value
    // object from such a response must not throw (regression: a missing
    // toelichting previously produced a TypeError while rendering the besluiten
    // on the zaak page).
    $response = [
        'url' => 'https://besluiten.example/api/v1/besluiten/abc',
        'identificatie' => 'B0001',
        'besluittype' => 'https://catalogi.example/api/v1/besluittypen/1',
        'zaak' => 'https://zaken.example/api/v1/zaken/xyz',
        'datum' => '2026-07-07',
        'ingangsdatum' => '2026-07-07',
    ];

    $besluit = new Besluit(...$response);

    expect($besluit->toelichting)->toBeNull()
        ->and($besluit->verzenddatum)->toBeNull()
        ->and($besluit->vervaldatum)->toBeNull()
        ->and($besluit->identificatie)->toBe('B0001')
        ->and($besluit->ingangsdatum)->toBe('2026-07-07');
});

test('accepts an explicit null toelichting and verzenddatum', function () {
    // The zaak-page render path passes the fields through by name, as null.
    $besluit = new Besluit(
        url: 'https://besluiten.example/api/v1/besluiten/abc',
        identificatie: 'B1',
        besluittype: 'https://catalogi.example/api/v1/besluittypen/1',
        zaak: 'https://zaken.example/api/v1/zaken/xyz',
        datum: '2026-07-07',
        ingangsdatum: '2026-07-07',
        toelichting: null,
        verzenddatum: null,
    );

    expect($besluit->toelichting)->toBeNull()
        ->and($besluit->verzenddatum)->toBeNull();
});

test('still accepts a besluit with all fields populated', function () {
    $besluit = new Besluit(
        url: 'https://besluiten.example/api/v1/besluiten/abc',
        identificatie: 'B1',
        besluittype: 'https://catalogi.example/api/v1/besluittypen/1',
        zaak: 'https://zaken.example/api/v1/zaken/xyz',
        datum: '2026-07-07',
        ingangsdatum: '2026-07-08',
        toelichting: 'Vergunning verleend',
        verzenddatum: '2026-07-09',
        vervaldatum: '2026-12-31',
    );

    expect($besluit->toelichting)->toBe('Vergunning verleend')
        ->and($besluit->verzenddatum)->toBe('2026-07-09')
        ->and($besluit->vervaldatum)->toBe('2026-12-31')
        ->and($besluit->toArray()['toelichting'])->toBe('Vergunning verleend');
});
