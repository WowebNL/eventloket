<?php

use App\ValueObjects\PostbusAddress;

describe('PostbusAddress value object', function () {
    test('weergavenaam formats correctly', function () {
        $postbus = new PostbusAddress('123', '1234AB', 'Amsterdam');

        expect($postbus->weergavenaam())->toBe('Postbus 123, 1234AB Amsterdam');
    });

    test('toArray returns all fields', function () {
        $postbus = new PostbusAddress('456', '9876ZX', 'Rotterdam');

        expect($postbus->toArray())->toBe([
            'postbusnummer' => '456',
            'postcode' => '9876ZX',
            'woonplaatsnaam' => 'Rotterdam',
        ]);
    });

    test('fromArray creates instance from array', function () {
        $postbus = PostbusAddress::fromArray([
            'postbusnummer' => '789',
            'postcode' => '5678CD',
            'woonplaatsnaam' => 'Utrecht',
        ]);

        expect($postbus->postbusnummer)->toBe('789')
            ->and($postbus->postcode)->toBe('5678CD')
            ->and($postbus->woonplaatsnaam)->toBe('Utrecht');
    });

    test('fromArray round-trips through toArray', function () {
        $original = ['postbusnummer' => '100', 'postcode' => '1000AA', 'woonplaatsnaam' => 'Den Haag'];
        $postbus = PostbusAddress::fromArray($original);

        expect($postbus->toArray())->toBe($original);
    });
});
