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

test('builds a natuurlijk_persoon rol from name when there is no KvK', function () {
    $state = FormState::fromSnapshot(['values' => [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ]]);

    $rol = InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, [
        'contactpersoon' => ['naam' => 'Jan Jansen'],
    ]);

    expect($rol['betrokkeneType'])->toBe('natuurlijk_persoon')
        ->and($rol['betrokkeneIdentificatie']['geslachtsnaam'])->toBe('Jansen')
        ->and($rol['betrokkeneIdentificatie']['voornamen'])->toBe('Jan');
});

test('returns null when there is no initiator data', function () {
    $state = FormState::fromSnapshot(['values' => []]);

    expect(InitiatorRolBuilder::build('https://zgw/zaken/1', 'https://zgw/roltype/1', $state, []))->toBeNull();
});
