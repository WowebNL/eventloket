<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\Services\Zgw\InitiatorRolBuilder;
use App\Services\Zgw\ZgwConnectionConfig;

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

// The contactpersoonRol.naam bound is decided per connection. OneGround's v1.5
// validator (ZaakRolRequestDtoValidator) caps it at 40 characters; every other
// backend follows the VNG 1.5 OAS / OpenZaak maximum of 200. The builder reads
// ZgwConnectionConfig::isOneGround(), so a connection is marked OneGround by
// setting its is_oneground config. contactpersoonRol travels with every
// betrokkeneType, so both bounds are asserted across the variants.
$longNaam = 'Rob van Nijnanten (Organisatie zonder kvk)';   // 42 characters
$boundedNaam40 = 'Rob van Nijnanten (Organisatie zonder kv';  // first 40

/** Mark a connection as a OneGround (RX Mission) backend for the duration of a test. */
function markOneGround(string $connectionName): void
{
    config(["zgw.connections.{$connectionName}.is_oneground" => true]);
}

test('caps contactpersoonRol.naam to 40 on a natuurlijk_persoon rol on a OneGround connection and keeps the full name elsewhere', function () use ($longNaam, $boundedNaam40) {
    expect(mb_strlen($longNaam))->toBe(42)
        ->and(mb_strlen($boundedNaam40))->toBe(40);

    markOneGround('rxmission');

    // The composed name is 42 characters, so afwijkendeNaamBetrokkene (max 625)
    // still carries it in full while contactpersoonRol.naam is bounded to 40.
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Rob',
        'watIsUwAchternaam' => 'van Nijnanten (Organisatie zonder kvk)',
    ]]);

    $rol = InitiatorRolBuilder::build('rxmission', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => $longNaam, 'emailadres' => 'rob@example.test'],
    ], 'EVL42');

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['contactpersoonRol']['naam'])->toBe($boundedNaam40)
        ->and(mb_strlen($rol['contactpersoonRol']['naam']))->toBe(40)
        ->and($rol['contactpersoonRol']['emailadres'])->toBe('rob@example.test')
        ->and($rol['afwijkendeNaamBetrokkene'])->toBe($longNaam)
        ->and($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe('van Nijnanten (Organisatie zonder kvk)')
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe('Rob')
        ->and($rol['betrokkeneIdentificatie']['anpIdentificatie'])->toBe('EVL42');
});

test('caps contactpersoonRol.naam to 40 on a vestiging rol on a OneGround connection and leaves the rest of the payload unchanged', function () use ($longNaam, $boundedNaam40) {
    markOneGround('rxmission');
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('rxmission', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => $longNaam],
    ]);

    expect($rol)->toBe([
        'zaak' => 'https://zgw/zaken/1',
        'betrokkeneType' => 'vestiging',
        'roltype' => 'https://zgw/roltype/1',
        'roltoelichting' => 'inzender formulier',
        'contactpersoonRol' => ['naam' => $boundedNaam40],
        'betrokkeneIdentificatie' => [
            'kvkNummer' => '12345678',
            'handelsnaam' => ['Woweb'],
        ],
    ]);
});

test('caps contactpersoonRol.naam to 40 on a niet_natuurlijk_persoon rol on a OneGround connection and leaves the rest of the payload unchanged', function () use ($longNaam, $boundedNaam40) {
    // The niet_natuurlijk_persoon variant is only built on the default
    // connection, so this proves the cap is wired into that variant by forcing
    // the default connection onto the OneGround bound.
    markOneGround('main');
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => $longNaam],
    ]);

    expect($rol)->toBe([
        'zaak' => 'https://zgw/zaken/1',
        'betrokkeneType' => 'niet_natuurlijk_persoon',
        'roltype' => 'https://zgw/roltype/1',
        'roltoelichting' => 'inzender formulier',
        'contactpersoonRol' => ['naam' => $boundedNaam40],
        'betrokkeneIdentificatie' => [
            'statutaireNaam' => 'Woweb',
            'annIdentificatie' => '12345678',
            'kvkNummer' => '12345678',
        ],
    ]);
});

test('leaves a contactpersoonRol.naam of exactly 40 characters byte-for-byte unchanged on a OneGround connection', function () use ($boundedNaam40) {
    // Boundary regression anchor: a name at the OneGround limit must not be touched.
    markOneGround('rxmission');
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('rxmission', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => $boundedNaam40],
    ]);

    expect($rol['contactpersoonRol']['naam'])->toBe($boundedNaam40)
        ->and(mb_strlen($rol['contactpersoonRol']['naam']))->toBe(40);
});

test('keeps a name between 41 and 200 characters in full on a non-OneGround connection', function () use ($longNaam) {
    // main is our own OpenZaak (not OneGround): the 42-character name that a
    // OneGround backend would reject is sent in full, not truncated at 40.
    expect(ZgwConnectionConfig::isOneGround('main'))->toBeFalse();

    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Rob',
        'watIsUwAchternaam' => 'van Nijnanten (Organisatie zonder kvk)',
    ]]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => $longNaam],
    ], 'EVL42');

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['contactpersoonRol']['naam'])->toBe($longNaam)
        ->and(mb_strlen($rol['contactpersoonRol']['naam']))->toBe(42);
});

test('caps contactpersoonRol.naam to 200 on a non-OneGround connection and keeps exactly 200 in full', function () {
    $name201 = str_repeat('a', 201);
    $name200 = str_repeat('a', 200);
    $state = FormState::fromSnapshot(['values' => []]);

    // 201 characters is bounded to the VNG/OpenZaak maximum of 200.
    $over = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => $name201],
    ]);

    // Exactly 200 characters is left byte-for-byte unchanged (boundary anchor).
    $atLimit = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => ['naam' => $name200],
    ]);

    expect($over['contactpersoonRol']['naam'])->toBe($name200)
        ->and(mb_strlen($over['contactpersoonRol']['naam']))->toBe(200)
        ->and($atLimit['contactpersoonRol']['naam'])->toBe($name200)
        ->and(mb_strlen($atLimit['contactpersoonRol']['naam']))->toBe(200);
});
