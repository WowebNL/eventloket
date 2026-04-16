<?php

declare(strict_types=1);

use App\EventForm\Transpiler\RuleDependencyAnalyzer;

describe('RuleDependencyAnalyzer', function () {
    test('simple var returns a single key', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys(['var' => 'soortEvenement']);

        expect($keys)->toBe(['soortEvenement']);
    });

    test('nested accessor returns the root key (not the nested path)', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys(['var' => 'gemeenteVariabelen.aanwezigen']);

        // We interesseren ons in welk top-level veld/variable gelezen wordt,
        // niet in welk sub-pad. De "scope" is het bovenste bakje.
        expect($keys)->toBe(['gemeenteVariabelen']);
    });

    test('== binary expression returns keys from both sides', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            '==' => [['var' => 'soortEvenement'], 'Markt of braderie'],
        ]);

        expect($keys)->toBe(['soortEvenement']);
    });

    test('and with multiple vars returns all distinct keys', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            'and' => [
                ['==' => [['var' => 'soortEvenement'], 'A']],
                ['!=' => [['var' => 'watIsDeNaamVanHetEvenementVergunning'], '']],
            ],
        ]);

        expect($keys)->toBeArray()
            ->and($keys)->toContain('soortEvenement')
            ->and($keys)->toContain('watIsDeNaamVanHetEvenementVergunning')
            ->and(count($keys))->toBe(2);
    });

    test('keys are deduplicated across the tree', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            'and' => [
                ['!!' => [['var' => 'adresVanDeGebouwEn']]],
                ['!=' => [['var' => 'adresVanDeGebouwEn'], 'None']],
            ],
        ]);

        expect($keys)->toBe(['adresVanDeGebouwEn']);
    });

    test('reduce expression extracts the array-var', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            '>=' => [
                ['reduce' => [
                    ['var' => 'evenementInGemeentenNamen'],
                    ['+' => [1, ['var' => 'accumulator']]],
                    0,
                ]],
                2,
            ],
        ]);

        // `accumulator` is een reduce-interne variabele, geen state-read;
        // filteren we eruit.
        expect($keys)->toBe(['evenementInGemeentenNamen']);
    });

    test('dynamic var via cat returns both the cat-inputs', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            'var' => ['cat' => ['gemeenten.', ['var' => 'userSelectGemeente']]],
        ]);

        // De cat-expression is een path-builder; we willen dat de referentie
        // naar userSelectGemeente zichtbaar wordt, want een wijziging daarop
        // triggert deze rule opnieuw.
        expect($keys)->toContain('userSelectGemeente');
    });

    test('map extracts outer array but ignores inner item-level vars', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            'map' => [
                ['var' => 'inGemeentenResponse.all.items'],
                ['var' => 'name'],  // item-level read, geen state-read
            ],
        ]);

        expect($keys)->toBe(['inGemeentenResponse']);
    });

    test('literal values produce no keys', function () {
        $keys = (new RuleDependencyAnalyzer)->readKeys([
            '==' => [1, 2],
        ]);

        expect($keys)->toBe([]);
    });

    test('empty / non-array input yields empty result', function () {
        expect((new RuleDependencyAnalyzer)->readKeys(null))->toBe([])
            ->and((new RuleDependencyAnalyzer)->readKeys(true))->toBe([])
            ->and((new RuleDependencyAnalyzer)->readKeys([]))->toBe([]);
    });
});
