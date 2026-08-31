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

test('sends no vestigingsNummer on a vestiging rol, and no verblijfsadres without an organisation address', function () {
    // The form does not ask for a vestigingsNummer, so it is never invented.
    // The verblijfsadres is built from the submitted organisation address, so a
    // submission without one still yields a valid rol without the field.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('vestigingsNummer')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('records the submitted organisation address as the vestiging verblijfsadres', function () {
    // RolVestiging carries a verblijfsadres, and the address the organiser
    // submits for the organisation is what goes in it (product decision). The
    // aoaIdentificatie is synthesised because the form gives a typed address
    // rather than a BAG object id, and names the kind of address it was built
    // from.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => '6411 CD',
            'huisnummer' => '32',
            'huisletter' => 'a',
            'huisnummertoevoeging' => 'bis',
            'straatnaam' => 'Coriovallumstraat',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];

    expect($rol['betrokkeneType'])->toBe('vestiging')
        ->and($rol['betrokkeneIdentificatie']['kvkNummer'])->toBe('12345678')
        ->and($rol['betrokkeneIdentificatie']['handelsnaam'])->toBe(['Woweb'])
        ->and($adres['aoaIdentificatie'])->toContain('-vestigingsadres-')
        ->and($adres['wplWoonplaatsNaam'])->toBe('Heerlen')
        ->and($adres['gorOpenbareRuimteNaam'])->toBe('Coriovallumstraat')
        ->and($adres['aoaPostcode'])->toBe('6411CD')
        ->and($adres['aoaHuisnummer'])->toBe(32)
        ->and($adres['aoaHuisletter'])->toBe('a')
        ->and($adres['aoaHuisnummertoevoeging'])->toBe('bis');
});

test('names the address kind in the synthesised aoaIdentificatie per variant', function () {
    // The two variants build their address through one helper, so the kind in
    // the identification is the only thing that tells them apart afterwards.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);
    $adres = [
        'postcode' => '6411CD',
        'huisnummer' => '32',
        'plaatsnaam' => 'Heerlen',
        'straatnaam' => 'Coriovallumstraat',
    ];

    $vestiging = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => $adres,
    ]);
    $persoon = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => $adres,
    ]);

    expect($vestiging['betrokkeneIdentificatie']['verblijfsadres']['aoaIdentificatie'])->toContain('-vestigingsadres-')
        ->and($persoon['betrokkeneIdentificatie']['verblijfsadres']['aoaIdentificatie'])->toContain('-persoonsadres-');
});

test('sends no verblijfsadres on a niet_natuurlijk_persoon rol', function () {
    // RolNietNatuurlijkPersoon defines no verblijfsadres property in any Zaken
    // API release this application targets, so the organisation address stays
    // off our own OpenZaak's variant even though it is now submitted.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '32',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($rol['betrokkeneType'])->toBe('niet_natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('sends a foreign organisation address as subVerblijfBuitenland, not as a verblijfsadres', function () {
    // verblijfsadres models a BAG address and cannot hold a foreign one, so the
    // standard puts it in subVerblijfBuitenland instead. RolVestiging carries
    // both, exactly as RolNatuurlijkPersoon does.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => '1000',
            'huisnummer' => '7',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => 'Teststad',
            'land' => 'België',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($rol['betrokkeneIdentificatie']['subVerblijfBuitenland'])->toBe([
            'lndLandcode' => '5010',
            'lndLandnaam' => 'België',
            'subAdresBuitenland_1' => 'Teststraat 7',
            'subAdresBuitenland_2' => '1000 Teststad',
        ])
        ->and($rol['betrokkeneIdentificatie']['handelsnaam'])->toBe(['Woweb']);
});

test('skips the vestiging verblijfsadres when the huisnummer is not a plain number in range', function () {
    // aoaHuisnummer is required on the address, so an organisation address that
    // cannot supply a valid one is left out rather than risking a 400 that
    // would abort the submit chain.
    $state = FormState::fromSnapshot(['values' => []]);

    $build = fn (string $huisnummer) => InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => $huisnummer,
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($build('32a')['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($build('1234567890')['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($build('99999')['betrokkeneIdentificatie']['verblijfsadres']['aoaHuisnummer'])->toBe(99999);
});

test('applies the same schema bounds to the vestiging verblijfsadres', function () {
    // The vestiging address runs through the same helper, so the required names
    // are cut to 80 while an oversized postcode, huisletter and
    // huisnummertoevoeging are dropped instead of cut.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => str_repeat('9', 12),
            'huisnummer' => '32',
            'huisletter' => 'abc',
            'huisnummertoevoeging' => 'achterzijde',
            'straatnaam' => str_repeat('s', 120),
            'plaatsnaam' => str_repeat('p', 120),
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];

    expect($adres['wplWoonplaatsNaam'])->toBe(str_repeat('p', 80))
        ->and($adres['gorOpenbareRuimteNaam'])->toBe(str_repeat('s', 80))
        ->and($adres)->not->toHaveKey('aoaPostcode')
        ->and($adres)->not->toHaveKey('aoaHuisletter')
        ->and($adres)->not->toHaveKey('aoaHuisnummertoevoeging')
        ->and($adres['aoaHuisnummer'])->toBe(32);
});

test('falls back to a placeholder openbare ruimte when the organisation address has no straatnaam', function () {
    // gorOpenbareRuimteNaam is required on the address, so the helper's
    // placeholder keeps the rest of the address sendable.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '32',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie']['verblijfsadres']['gorOpenbareRuimteNaam'])->toBe('adres');
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

test('sends a foreign address as subVerblijfBuitenland, not as a verblijfsadres', function () {
    // The two blocks are mutually exclusive: verblijfsadres is a BAG address,
    // so a foreign one is expressed as subVerblijfBuitenland. The house number
    // and its additions join the street on the first line, the postcode joins
    // the place on the second: the schema has no separate fields for them.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000',
            'huisnummer' => '7',
            'huisletter' => 'b',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => 'Teststad',
            'land' => 'België',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($rol['betrokkeneIdentificatie']['subVerblijfBuitenland'])->toBe([
            'lndLandcode' => '5010',
            'lndLandnaam' => 'België',
            'subAdresBuitenland_1' => 'Teststraat 7 b',
            'subAdresBuitenland_2' => '1000 Teststad',
        ]);
});

test('resolves the country through the BRP table rather than as plain text', function () {
    // lndLandcode comes from the BRP Land/Gebied table, which is also what
    // decides whether an address is foreign at all: an ISO code or a differing
    // spelling of the Netherlands stays an ordinary verblijfsadres, while a
    // country published under a longer official name is still recognised.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $build = fn (string $land) => InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000AA',
            'huisnummer' => '7',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => 'Teststad',
            'land' => $land,
        ],
    ])['betrokkeneIdentificatie'];

    expect($build('NL'))->toHaveKey('verblijfsadres')
        ->and($build('nederland'))->toHaveKey('verblijfsadres')
        ->and($build('Duitsland')['subVerblijfBuitenland']['lndLandcode'])->toBe('9089')
        ->and($build('Duitsland')['subVerblijfBuitenland']['lndLandnaam'])->toBe('Bondsrepubliek Duitsland')
        ->and($build('DE')['subVerblijfBuitenland']['lndLandcode'])->toBe('9089');
});

test('leaves out a foreign address whose country cannot be resolved to a BRP code', function () {
    // lndLandcode is required on subVerblijfBuitenland, so a country the table
    // does not know leaves nothing that may be sent. Inventing a code would put
    // a different country on the zaak, and sending the block without one is a
    // 400 that aborts the submit chain, so the address is left out, exactly as
    // a foreign address was before this block was built.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000',
            'huisnummer' => '7',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => 'Teststad',
            'land' => 'Neverland',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('subVerblijfBuitenland');
});

test('cuts the foreign address lines to the schema bound instead of dropping them', function () {
    // The schema has no separate street/place fields to preserve here, so a
    // shortened line is the only thing that can be sent and it still identifies
    // the address.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => str_repeat('9', 20),
            'huisnummer' => '7',
            'straatnaam' => str_repeat('s', 120),
            'plaatsnaam' => str_repeat('p', 120),
            'land' => 'België',
        ],
    ]);

    $buitenland = $rol['betrokkeneIdentificatie']['subVerblijfBuitenland'];

    expect($buitenland['subAdresBuitenland_1'])->toBe(str_repeat('s', 35))
        ->and($buitenland['subAdresBuitenland_2'])->toBe(str_repeat('9', 20).' '.str_repeat('p', 14))
        ->and(mb_strlen($buitenland['subAdresBuitenland_2']))->toBe(35);
});

test('builds a foreign address from whatever lines the form supplied', function () {
    // Only the country is required to build the block; the rest of the address
    // is optional free text, so a partial address still travels.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'plaatsnaam' => 'Teststad',
            'land' => 'België',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie']['subVerblijfBuitenland'])->toBe([
        'lndLandcode' => '5010',
        'lndLandnaam' => 'België',
        'subAdresBuitenland_1' => 'Teststad',
    ]);
});

test('skips the verblijfsadres for a foreign address', function () {
    // A foreign address with a huisnummer the BAG block would accept still must
    // not become a verblijfsadres.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000',
            'huisnummer' => '7',
            'plaatsnaam' => 'Teststad',
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
$longNaam = 'Jan van Testeling modelpersoon budget vier';   // 42 characters
$boundedNaam40 = 'Jan van Testeling modelpersoon budget vi';  // first 40

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
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'van Testeling modelpersoon budget vier',
    ]]);

    $rol = InitiatorRolBuilder::build('rxmission', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => $longNaam, 'emailadres' => 'jan@example.test'],
    ], 'EVL42');

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['contactpersoonRol']['naam'])->toBe($boundedNaam40)
        ->and(mb_strlen($rol['contactpersoonRol']['naam']))->toBe(40)
        ->and($rol['contactpersoonRol']['emailadres'])->toBe('jan@example.test')
        ->and($rol['afwijkendeNaamBetrokkene'])->toBe($longNaam)
        ->and($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe('van Testeling modelpersoon budget vier')
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe('Jan')
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
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'van Testeling modelpersoon budget vier',
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

// Every other field the organiser fills in also has a hard bound in the Zaken
// API schema, and the form accepts far longer answers than any of them. One
// oversized field is enough for the API to reject the whole rol, which aborts
// the submit chain and leaves the zaak without an initiator, so each variant is
// asserted field by field and then swept as a whole.

/**
 * The bound the Zaken API schema puts on each string in the rol payload that
 * carries organiser input, as a dotted path into the payload.
 *
 * @return array<string, int>
 */
function rolPayloadBounds(): array
{
    return [
        'afwijkendeNaamBetrokkene' => 625,
        'contactpersoonRol.naam' => 200,
        'contactpersoonRol.emailadres' => 254,
        'contactpersoonRol.telefoonnummer' => 20,
        'betrokkeneIdentificatie.geslachtsnaam' => 200,
        'betrokkeneIdentificatie.voornamen' => 200,
        'betrokkeneIdentificatie.statutaireNaam' => 500,
        'betrokkeneIdentificatie.annIdentificatie' => 17,
        'betrokkeneIdentificatie.kvkNummer' => 8,
        'betrokkeneIdentificatie.handelsnaam.0' => 625,
        'betrokkeneIdentificatie.verblijfsadres.wplWoonplaatsNaam' => 80,
        'betrokkeneIdentificatie.verblijfsadres.gorOpenbareRuimteNaam' => 80,
        'betrokkeneIdentificatie.verblijfsadres.aoaPostcode' => 7,
        'betrokkeneIdentificatie.verblijfsadres.aoaHuisletter' => 1,
        'betrokkeneIdentificatie.verblijfsadres.aoaHuisnummertoevoeging' => 4,
        'betrokkeneIdentificatie.subVerblijfBuitenland.lndLandcode' => 4,
        'betrokkeneIdentificatie.subVerblijfBuitenland.lndLandnaam' => 40,
        'betrokkeneIdentificatie.subVerblijfBuitenland.subAdresBuitenland_1' => 35,
        'betrokkeneIdentificatie.subVerblijfBuitenland.subAdresBuitenland_2' => 35,
        'betrokkeneIdentificatie.subVerblijfBuitenland.subAdresBuitenland_3' => 35,
    ];
}

/**
 * Assert that no string in the payload is longer than its schema bound, and
 * that an emailadres that did survive is still a usable address. The offending
 * paths are collected first so a failure names every field at once instead of
 * stopping at the first.
 *
 * The email check is what a plain length check cannot catch: the schema types
 * contactpersoonRol.emailadres as format email, so an address cut at 254 would
 * pass the bound while no longer being an address (or being someone else's).
 * Dropping it leaves the key absent, which passes both checks.
 *
 * @param  array<string, mixed>  $rol
 */
function expectRolWithinSchemaBounds(array $rol): void
{
    $violations = [];

    $emailadres = data_get($rol, 'contactpersoonRol.emailadres');
    if ($emailadres !== null && filter_var($emailadres, FILTER_VALIDATE_EMAIL) === false) {
        $violations['contactpersoonRol.emailadres'] = 'not a valid email address';
    }

    foreach (rolPayloadBounds() as $path => $max) {
        $value = data_get($rol, $path);

        if ($value === null) {
            continue;
        }

        if (! is_string($value)) {
            $violations[$path] = 'not a string: '.gettype($value);

            continue;
        }

        if (mb_strlen($value) > $max) {
            $violations[$path] = mb_strlen($value).' characters, bound is '.$max;
        }
    }

    expect($violations)->toBe([]);
}

/**
 * An initiator block in which every organiser answer is 1000 characters, the
 * length the form allows on its longest text fields.
 *
 * @return array<string, mixed>
 */
function oversizedInitiator(): array
{
    $long = str_repeat('a', 1000);

    return [
        'organisatie_naam' => $long,
        'natuurlijk_persoon_adres' => [
            'postcode' => $long,
            'huisnummer' => '32',
            'huisletter' => $long,
            'huisnummertoevoeging' => $long,
            'straatnaam' => $long,
            'plaatsnaam' => $long,
        ],
        'contactpersoon' => [
            'naam' => $long,
            'emailadres' => $long.'@example.test',
            'telefoonnummer' => $long,
        ],
    ];
}

/** The form state behind {@see oversizedInitiator()}. */
function oversizedState(): FormState
{
    $long = str_repeat('a', 1000);

    return FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => $long,
        'watIsUwAchternaam' => $long,
    ]]);
}

test('keeps every natuurlijk_persoon field inside its schema bound for a maximum length form submission', function () {
    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', oversizedState(), oversizedInitiator(), 'EVL42');

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon');
    expectRolWithinSchemaBounds($rol);
});

test('keeps every vestiging field inside its schema bound for a maximum length form submission', function () {
    $initiator = oversizedInitiator();
    $initiator['kvk'] = '12345678';

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', oversizedState(), $initiator);

    expect($rol['betrokkeneType'])->toBe('vestiging');
    expectRolWithinSchemaBounds($rol);
});

test('keeps every niet_natuurlijk_persoon field inside its schema bound for a maximum length form submission', function () {
    $initiator = oversizedInitiator();
    $initiator['kvk'] = '12345678';

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', oversizedState(), $initiator);

    expect($rol['betrokkeneType'])->toBe('niet_natuurlijk_persoon');
    expectRolWithinSchemaBounds($rol);
});

test('cuts geslachtsnaam and voornamen to 200 and afwijkendeNaamBetrokkene to 625', function () {
    $voornaam = str_repeat('v', 300);
    $achternaam = str_repeat('a', 400);

    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => $voornaam,
        'watIsUwAchternaam' => $achternaam,
    ]]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => 'Contact Persoon'],
    ], 'EVL42');

    expect($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe(str_repeat('a', 200))
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe(str_repeat('v', 200))
        // The composed name is 701 characters (300 + space + 400).
        ->and($rol['afwijkendeNaamBetrokkene'])->toBe(mb_substr($voornaam.' '.$achternaam, 0, 625))
        ->and(mb_strlen($rol['afwijkendeNaamBetrokkene']))->toBe(625);
});

test('leaves name fields at exactly their bound byte-for-byte unchanged', function () {
    // Boundary anchor next to the cut above: nothing is touched at the limit.
    $voornaam = str_repeat('v', 200);
    $achternaam = str_repeat('a', 200);

    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => $voornaam,
        'watIsUwAchternaam' => $achternaam,
    ]]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => 'Contact Persoon'],
    ], 'EVL42');

    expect($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe($achternaam)
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe($voornaam)
        // 401 characters, still under the 625 bound.
        ->and($rol['afwijkendeNaamBetrokkene'])->toBe($voornaam.' '.$achternaam);
});

test('cuts the verblijfsadres names to 80 and drops a postcode that does not fit', function () {
    // wplWoonplaatsNaam and gorOpenbareRuimteNaam are required on the address,
    // so they are cut; aoaPostcode is optional and a cut postcode would point
    // at another place, so it is dropped instead.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => str_repeat('9', 12),
            'huisnummer' => '32',
            'straatnaam' => str_repeat('s', 120),
            'plaatsnaam' => str_repeat('p', 120),
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];

    expect($adres['wplWoonplaatsNaam'])->toBe(str_repeat('p', 80))
        ->and($adres['gorOpenbareRuimteNaam'])->toBe(str_repeat('s', 80))
        ->and($adres)->not->toHaveKey('aoaPostcode')
        ->and($adres['aoaHuisnummer'])->toBe(32);
});

test('drops the verblijfsadres when the huisnummer is outside the schema range', function () {
    // aoaHuisnummer is required on the address and the schema caps it at 99999,
    // so an address that cannot supply a valid one is left out entirely, just
    // as it is for a non-numeric huisnummer.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '1234567890',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres');
});

test('keeps a huisnummer at exactly the schema maximum', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '6411CD',
            'huisnummer' => '99999',
            'plaatsnaam' => 'Heerlen',
        ],
    ]);

    expect($rol['betrokkeneIdentificatie']['verblijfsadres']['aoaHuisnummer'])->toBe(99999);
});

test('drops an oversized contactpersoonRol emailadres and cuts the other contact details to their schema bounds', function () {
    // The schema types emailadres as format email as well as maxLength 254, so
    // cutting a long address at 254 would remove the part after the @ and leave
    // a value that is not an address (or, on a shorter cut, someone else's).
    // The field is optional, so it is dropped instead.
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => [
            'naam' => 'Contact Persoon',
            'emailadres' => str_repeat('e', 300).'@example.test',
            'telefoonnummer' => str_repeat('0', 40),
        ],
    ]);

    expect($rol['contactpersoonRol'])->not->toHaveKey('emailadres')
        ->and($rol['contactpersoonRol']['telefoonnummer'])->toBe(str_repeat('0', 20))
        ->and($rol['contactpersoonRol']['naam'])->toBe('Contact Persoon');
});

test('keeps a contactpersoonRol emailadres at or under its bound byte-for-byte unchanged', function () {
    // Boundary anchor next to the drop above: an address of exactly 254
    // characters is inside the bound and must be sent as typed.
    $atLimit = str_repeat('e', 254 - mb_strlen('@example.test')).'@example.test';
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'contactpersoon' => [
            'naam' => 'Contact Persoon',
            'emailadres' => $atLimit,
        ],
    ]);

    expect(mb_strlen($atLimit))->toBe(254)
        ->and($rol['contactpersoonRol']['emailadres'])->toBe($atLimit);
});

test('cuts the vestiging handelsnaam to 625 and keeps a name at the bound unchanged', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $over = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => str_repeat('o', 700),
    ]);

    $atLimit = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => str_repeat('o', 625),
    ]);

    expect($over['betrokkeneIdentificatie']['handelsnaam'])->toBe([str_repeat('o', 625)])
        ->and($atLimit['betrokkeneIdentificatie']['handelsnaam'])->toBe([str_repeat('o', 625)]);
});

test('cuts the niet_natuurlijk_persoon statutaireNaam to 500 and keeps a name at the bound unchanged', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $over = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => str_repeat('o', 700),
    ]);

    $atLimit = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => str_repeat('o', 500),
    ]);

    expect($over['betrokkeneIdentificatie']['statutaireNaam'])->toBe(str_repeat('o', 500))
        ->and($atLimit['betrokkeneIdentificatie']['statutaireNaam'])->toBe(str_repeat('o', 500));
});

test('drops a KvK number that does not fit the schema bound instead of cutting it', function () {
    // Cutting would put a different, existing company number on the zaak, so an
    // oversized value is dropped and the rol registers on the name alone, the
    // same way it does for an already hashed number.
    $state = FormState::fromSnapshot(['values' => []]);

    $vestiging = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '123456789',
        'organisatie_naam' => 'Woweb',
    ]);

    $nietNatuurlijkPersoon = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '123456789',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($vestiging['betrokkeneIdentificatie'])->toBe(['handelsnaam' => ['Woweb']])
        ->and($nietNatuurlijkPersoon['betrokkeneIdentificatie'])->toBe(['statutaireNaam' => 'Woweb']);
});

test('keeps a KvK number of exactly eight characters', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
    ]);

    expect($rol['betrokkeneIdentificatie']['kvkNummer'])->toBe('12345678');
});

test('keeps every foreign-address field inside its schema bound for a maximum length form submission', function () {
    $initiator = oversizedInitiator();
    $initiator['natuurlijk_persoon_adres']['land'] = 'België';

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', oversizedState(), $initiator, 'EVL42');

    expect($rol['betrokkeneIdentificatie'])->toHaveKey('subVerblijfBuitenland');
    expectRolWithinSchemaBounds($rol);
});

// The address fields the form asks for are separate inputs, and they stay
// separate all the way to the rol: the place name and the house number are
// carried by two different properties of the ZGW address. These anchor that,
// so a report of one turning up in the other can be answered from the payload
// this application builds rather than from reasoning about it.

test('maps the place name and the house number onto their own address properties', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000AA',
            'huisnummer' => '5',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => 'Teststad',
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];

    expect($adres['wplWoonplaatsNaam'])->toBe('Teststad')
        ->and($adres['aoaHuisnummer'])->toBe(5)
        // The one that would show up as a corrupted woonplaats: the place name
        // is never the house number, in either direction.
        ->and($adres['wplWoonplaatsNaam'])->not->toBe('5')
        ->and($adres['gorOpenbareRuimteNaam'])->toBe('Teststraat')
        ->and($adres['aoaPostcode'])->toBe('1000AA');
});

test('keeps the place name and the house number apart on both address-carrying variants', function () {
    // Both variants build their address through one helper, so the mapping
    // cannot differ between them; asserted on both anyway, since a rol on an
    // organisation and one on a private aanvrager are separate payloads.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);
    $adres = [
        'postcode' => '1000AA',
        'huisnummer' => '5',
        'straatnaam' => 'Teststraat',
        'plaatsnaam' => 'Teststad',
    ];

    $vestiging = InitiatorRolBuilder::build('heerlen', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'kvk' => '12345678',
        'organisatie_naam' => 'Woweb',
        'organisatie_adres' => $adres,
    ])['betrokkeneIdentificatie']['verblijfsadres'];

    $persoon = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => $adres,
    ])['betrokkeneIdentificatie']['verblijfsadres'];

    foreach ([$vestiging, $persoon] as $built) {
        expect($built['wplWoonplaatsNaam'])->toBe('Teststad')
            ->and($built['aoaHuisnummer'])->toBe(5);
    }
});

test('carries a numeric-looking place name through as the place name', function () {
    // A place name that happens to look like a house number is still the place
    // name: nothing in the mapping reads a value's shape to decide where it
    // goes, so an address cannot be repaired or corrupted on the way out.
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Testachternaam']]);

    $rol = InitiatorRolBuilder::build('main', 'https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'natuurlijk_persoon_adres' => [
            'postcode' => '1000AA',
            'huisnummer' => '12',
            'straatnaam' => 'Teststraat',
            'plaatsnaam' => '5',
        ],
    ]);

    $adres = $rol['betrokkeneIdentificatie']['verblijfsadres'];

    expect($adres['wplWoonplaatsNaam'])->toBe('5')
        ->and($adres['aoaHuisnummer'])->toBe(12);
});
