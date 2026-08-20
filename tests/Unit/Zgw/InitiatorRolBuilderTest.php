<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\Services\Zgw\InitiatorRolBuilder;

test('keeps the default connection on the niet_natuurlijk_persoon payload it already received', function () {
    // Regression anchor: our own OpenZaak read this exact payload before, so
    // the whole rol is asserted, not a few keys. RolNietNatuurlijkPersoon has
    // no kvkNummer property in any Zaken API release from 1.0 up to and
    // including 1.7, so annIdentificatie carries the number and the
    // non-standard kvkNummer rides along for our own instance only.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => 'Organisator Test'],
    ]);

    expect($rol)->toBe([
        'zaak' => 'https://zgw/zaken/1',
        'betrokkeneType' => 'niet_natuurlijk_persoon',
        'roltype' => 'https://zgw/roltype/1',
        'roltoelichting' => 'inzender formulier',
        'contactpersoonRol' => ['naam' => 'Organisator Test'],
        'betrokkeneIdentificatie' => [
            'statutaireNaam' => 'Woweb',
            'annIdentificatie' => '12345678',
            'kvkNummer' => '12345678',
        ],
    ]);
});

test('builds a vestiging rol with kvkNummer and handelsnaam on a municipality connection', function () {
    // RolVestiging is the only betrokkeneType in the Zaken API that defines a
    // kvkNummer property (since 1.3.0), so this is the one payload in which a
    // receiving instance can store the number as a company number. No
    // annIdentificatie and no statutaireNaam: those belong to
    // niet_natuurlijk_persoon and are not part of RolVestiging.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => 'Organisator Test'],
    ]);

    expect($rol)->toBe([
        'zaak' => 'https://zgw/zaken/1',
        'betrokkeneType' => 'vestiging',
        'roltype' => 'https://zgw/roltype/1',
        'roltoelichting' => 'inzender formulier',
        'contactpersoonRol' => ['naam' => 'Organisator Test'],
        'betrokkeneIdentificatie' => [
            'kvkNummer' => '12345678',
            'handelsnaam' => ['Woweb'],
        ],
    ]);
});

test('sends no vestigingsNummer or verblijfsadres on a vestiging rol', function () {
    // The form asks for neither, and the organisation address is not
    // necessarily the address of the vestiging, so nothing is invented.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('vestigingsNummer')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('registers a vestiging on handelsnaam alone when the KvK number is already hashed', function () {
    // A rerun on a snapshot whose KvK was hashed (a job retry, or
    // zaak:create-doorkomst-zaken on an existing zaak) must not send the hash to
    // ZGW as if it were a KvK number. RolVestiging requires no field, so the rol
    // on handelsnaam alone is valid.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => 'hash:9f3c1d2e',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneType'])->toBe('vestiging')
        ->and($rol['betrokkeneIdentificatie'])->toBe(['handelsnaam' => ['Woweb']]);
});

test('omits an already hashed KvK number and keeps the organisation rol', function () {
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

test('builds the same natuurlijk_persoon rol whatever the connection is', function () {
    // Only the organisation variant branches on the connection; a private
    // aanvrager gets one payload everywhere.
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ]]);
    $initiator = ['contactpersoon' => ['naam' => 'Jan Jansen']];

    $default = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, $initiator, 'EVL42');
    $municipality = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, $initiator, 'EVL42');

    expect($municipality)->toBe($default);
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
