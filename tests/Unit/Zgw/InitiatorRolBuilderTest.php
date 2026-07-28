<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\Services\Zgw\InitiatorRolBuilder;

test('builds a niet_natuurlijk_persoon rol from a KvK initiator', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
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

test('builds a natuurlijk_persoon rol with anpIdentificatie, display name and verblijfsadres', function () {
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ]]);

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
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

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => 'Jan Jansen'],
    ]);

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('verblijfsadres')
        ->and($rol['betrokkeneIdentificatie'])->not->toHaveKey('anpIdentificatie');
});

test('skips the verblijfsadres for a foreign address', function () {
    $state = FormState::fromSnapshot(['values' => ['watIsUwAchternaam' => 'Jansen']]);

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
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

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
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

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
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

    expect(InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, []))->toBeNull();
});

test('derives a stable anpIdentificatie from the user id', function () {
    expect(InitiatorRolBuilder::anpIdentificatieForUser(42))->toBe('EVL42')
        ->and(InitiatorRolBuilder::anpIdentificatieForUser(null))->toBeNull();
});
