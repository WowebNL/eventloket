<?php

declare(strict_types=1);

use App\Services\Zgw\BrpLandGebied;

test('resolves a country by its published name', function () {
    expect(BrpLandGebied::resolve('België'))->toBe(['code' => '5010', 'naam' => 'België'])
        ->and(BrpLandGebied::resolve('Nederland'))->toBe(['code' => '6030', 'naam' => 'Nederland']);
});

test('ignores case, surrounding whitespace and accents', function () {
    // The form asks for the country as free text, so the typed value is folded
    // before it is compared.
    expect(BrpLandGebied::resolve('  belgie '))->toBe(['code' => '5010', 'naam' => 'België'])
        ->and(BrpLandGebied::resolve('NEDERLAND'))->toBe(['code' => '6030', 'naam' => 'Nederland']);
});

test('resolves a two-letter value as an ISO 3166-1 alpha-2 code', function () {
    expect(BrpLandGebied::resolve('be'))->toBe(['code' => '5010', 'naam' => 'België'])
        ->and(BrpLandGebied::resolve('NL')['code'])->toBe(BrpLandGebied::NETHERLANDS);
});

test('accepts an everyday name for a country published under a longer official one', function () {
    // The table publishes the official name; a name that occurs inside exactly
    // one entry identifies that entry unambiguously.
    expect(BrpLandGebied::resolve('Duitsland'))
        ->toBe(['code' => '9089', 'naam' => 'Bondsrepubliek Duitsland']);
});

test('refuses a name that fits several countries rather than guessing', function () {
    // Several entries carry this word, so it identifies none of them.
    expect(BrpLandGebied::resolve('Guinea'))->toBeNull();
});

test('returns nothing for an empty or unknown country', function () {
    expect(BrpLandGebied::resolve(null))->toBeNull()
        ->and(BrpLandGebied::resolve(''))->toBeNull()
        ->and(BrpLandGebied::resolve('   '))->toBeNull()
        ->and(BrpLandGebied::resolve('Geen land'))->toBeNull();
});

test('answers a code and a name the ZGW schema accepts', function () {
    // SubVerblijfBuitenland bounds lndLandcode at 4 characters and lndLandnaam
    // at 40, and both are required, so every entry has to fit as it stands.
    foreach (['België', 'Nederland', 'Duitsland', 'Frankrijk', 'Verenigd Koninkrijk'] as $land) {
        $entry = BrpLandGebied::resolve($land);

        expect($entry['code'])->toBeString()
            ->and(strlen($entry['code']))->toBeLessThanOrEqual(4)
            ->and(mb_strlen($entry['naam']))->toBeLessThanOrEqual(40);
    }
});
