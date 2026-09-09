<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Submit\EventAddressFormatter;

function stateWithGebouwen(mixed $gebouwen): FormState
{
    return FormState::fromSnapshot(['values' => ['adresVanDeGebouwEn' => $gebouwen]]);
}

test('formats a single BAG address as street, number, postcode and city', function () {
    $state = stateWithGebouwen([
        [
            'naamVanDeLocatieGebouw' => 'Marktplein',
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6411 CD',
                'huisnummer' => '32',
                'straatnaam' => 'Coriovallumstraat',
                'woonplaatsnaam' => 'Heerlen',
            ],
        ],
    ]);

    expect(EventAddressFormatter::fromState($state))->toBe('Coriovallumstraat 32, 6411CD Heerlen');
});

test('includes the huisletter and the toevoeging', function () {
    $state = stateWithGebouwen([
        [
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6411CD',
                'huisnummer' => '32',
                'huisletter' => 'A',
                'huisnummertoevoeging' => '3',
                'straatnaam' => 'Coriovallumstraat',
                'woonplaatsnaam' => 'Heerlen',
            ],
        ],
    ]);

    expect(EventAddressFormatter::fromState($state))->toBe('Coriovallumstraat 32A-3, 6411CD Heerlen');
});

test('joins every address of the aanvraag with a comma', function () {
    $state = stateWithGebouwen([
        [
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6411CD',
                'huisnummer' => '32',
                'straatnaam' => 'Coriovallumstraat',
                'woonplaatsnaam' => 'Heerlen',
            ],
        ],
        [
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6221AB',
                'huisnummer' => '1',
                'straatnaam' => 'Vrijthof',
                'woonplaatsnaam' => 'Maastricht',
            ],
        ],
    ]);

    expect(EventAddressFormatter::fromState($state))
        ->toBe('Coriovallumstraat 32, 6411CD Heerlen, Vrijthof 1, 6221AB Maastricht');
});

test('reads the old Open Forms address keys', function () {
    $state = stateWithGebouwen([
        [
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6411CD',
                'houseNumber' => '32',
                'houseLetter' => 'A',
                'streetName' => 'Coriovallumstraat',
                'city' => 'Heerlen',
            ],
        ],
    ]);

    expect(EventAddressFormatter::fromState($state))->toBe('Coriovallumstraat 32A, 6411CD Heerlen');
});

test('leaves out parts the address is missing', function () {
    // An address whose PDOK lookup never resolved can miss street and city.
    $state = stateWithGebouwen([
        ['adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => ['postcode' => '6411CD', 'huisnummer' => '32']],
    ]);

    expect(EventAddressFormatter::fromState($state))->toBe('32, 6411CD');
});

test('returns null when the aanvraag has no BAG address', function () {
    expect(EventAddressFormatter::fromState(stateWithGebouwen(null)))->toBeNull()
        ->and(EventAddressFormatter::fromState(stateWithGebouwen([])))->toBeNull()
        ->and(EventAddressFormatter::fromState(stateWithGebouwen([['naamVanDeLocatieGebouw' => 'Marktplein']])))->toBeNull();
});
