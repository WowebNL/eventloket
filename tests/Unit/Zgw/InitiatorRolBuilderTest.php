<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\Services\Zgw\InitiatorRolBuilder;

test('builds a niet_natuurlijk_persoon rol from a KvK initiator', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => 'Organisator Test'],
    ]);

    expect($rol['betrokkeneType'])->toBe('niet_natuurlijk_persoon')
        ->and($rol['roltype'])->toBe('https://zgw/roltype/1')
        ->and($rol['betrokkeneIdentificatie']['kvkNummer'])->toBe('12345678')
        ->and($rol['betrokkeneIdentificatie']['statutaireNaam'])->toBe('Woweb')
        ->and($rol['contactpersoonRol'])->toBe(['naam' => 'Organisator Test']);
});

test('carries the KvK number in annIdentificatie, next to kvkNummer on the default connection', function () {
    // RolNietNatuurlijkPersoon has no kvkNummer property in any Zaken API
    // release from 1.0 up to and including 1.7 (kvkNummer only exists on
    // RolVestiging, added in 1.3.0), so a conformant backend drops it and the
    // organisation ends up on the zaak without a company number at all. The
    // standard does define annIdentificatie here, so the number has to travel
    // in that property. kvkNummer is a non-standard extra kept for our own
    // OpenZaak instance only.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneIdentificatie']['annIdentificatie'])->toBe('12345678')
        ->and($rol['betrokkeneIdentificatie']['kvkNummer'])->toBe('12345678');
});

test('sends only annIdentificatie to a municipality connection', function () {
    // Any connection other than the default belongs to a municipality running
    // its own ZGW instance, which gets the standard payload without the
    // non-standard kvkNummer.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneType'])->toBe('niet_natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie']['annIdentificatie'])->toBe('12345678')
        ->and($rol['betrokkeneIdentificatie']['statutaireNaam'])->toBe('Woweb')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('kvkNummer');
});

test('omits an already hashed KvK number on a municipality connection too', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => 'hash:9f3c1d2e',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneIdentificatie']['statutaireNaam'])->toBe('Woweb')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('annIdentificatie')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('kvkNummer');
});

test('omits an already hashed KvK number and keeps the organisation rol', function () {
    // A rerun on a snapshot whose KvK was hashed (a job retry, or
    // zaak:create-doorkomst-zaken on an existing zaak) must not send the hash to
    // ZGW as if it were a KvK number.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => 'hash:9f3c1d2e',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneType'])->toBe('niet_natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie']['statutaireNaam'])->toBe('Woweb')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('kvkNummer')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('annIdentificatie');
});

test('builds a natuurlijk_persoon rol with anpIdentificatie, display name and verblijfsadres', function () {
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ]]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '6411 CD',
            'huisnummer' => '32',
            'huisletter' => 'a',
            'huisnummertoevoeging' => 'bis',
            'straatnaam' => 'Coriovallumstraat',
            'plaatsnaam' => 'Heerlen',
        ],
        'contactpersoon' => ['naam' => 'Jan Jansen'],
    ], 'EVL42');

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['afwijkendeNaamBetrokkene'])->toBe('Jan Jansen')
        ->and($rol['betrokkeneIdentificatie']['anpIdentificatie'])->toBe('EVL42')
        ->and($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe('Jansen')
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe('Jan');

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];
    expect($adres['wplWoonplaatsNaam'])->toBe('Heerlen')
        ->and($adres['gorOpenbareRuimteNaam'])->toBe('Coriovallumstraat')
        ->and($adres['aoaPostcode'])->toBe('6411CD')
        ->and($adres['aoaHuisnummer'])->toBe(32)
        ->and($adres['aoaHuisletter'])->toBe('a')
        ->and($adres['aoaHuisnummertoevoeging'])->toBe('bis');
});

test('builds a natuurlijk_persoon rol without verblijfsadres when no address is present', function () {
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ]]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => 'Jan Jansen'],
    ]);

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('anpIdentificatie');
});

test('skips the verblijfsadres for a foreign address', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '4000',
            'huisnummer' => '7',
            'plaatsnaam' => 'Luik',
            'land' => 'België',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('skips the verblijfsadres when the huisnummer is not numeric', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '32a',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('drops huisletter and toevoeging values that exceed the ZGW schema bounds', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '32',
            'huisletter' => 'abc',
            'huisnummertoevoeging' => 'achterzijde',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];
    expect($adres)->not->toHaveKey('aoaHuisletter')
        ->and($adres)->not->toHaveKey('aoaHuisnummertoevoeging')
        ->and($adres['aoaHuisnummer'])->toBe(32);
});

test('returns null when there is no initiator data', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    expect(InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, []))->toBeNull();
});

test('derives a stable anpIdentificatie from the user id', function () {
    expect(InitiatorRolBuilder::anpIdentificatieForUser(42))->toBe('EVL42')
        ->and(InitiatorRolBuilder::anpIdentificatieForUser(null))->toBeNull();
});
