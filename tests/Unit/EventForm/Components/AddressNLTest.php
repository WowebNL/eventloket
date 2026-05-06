<?php

declare(strict_types=1);

use App\EventForm\Components\AddressNL;
use Filament\Schemas\Components\Fieldset;

describe('AddressNL', function () {
    test('make returns a Fieldset', function () {
        $component = AddressNL::make('adresVanHetGebouw');

        expect($component)->toBeInstanceOf(Fieldset::class);
    });

    test('uses provided label when given', function () {
        $component = AddressNL::make('adresKey', 'Adres van het evenement');

        expect($component->getLabel())->toBe('Adres van het evenement');
    });

    test('fieldKeys exposes all sub-field paths under a given prefix', function () {
        $keys = AddressNL::fieldKeys('adresVanHetGebouw');

        expect($keys)->toContain('adresVanHetGebouw.postcode')
            ->and($keys)->toContain('adresVanHetGebouw.huisnummer')
            ->and($keys)->toContain('adresVanHetGebouw.huisletter')
            ->and($keys)->toContain('adresVanHetGebouw.huisnummertoevoeging')
            ->and($keys)->toContain('adresVanHetGebouw.straatnaam')
            ->and($keys)->toContain('adresVanHetGebouw.woonplaatsnaam');
    });

    test('postcode and huisnummer are the required subfields', function () {
        expect(AddressNL::REQUIRED_SUBFIELDS)->toBe(['postcode', 'huisnummer']);
    });

    test('huisletter is not in the required subfields list', function () {
        expect(AddressNL::REQUIRED_SUBFIELDS)->not->toContain('huisletter')
            ->and(AddressNL::REQUIRED_SUBFIELDS)->not->toContain('huisnummertoevoeging');
    });
});
