<?php

declare(strict_types=1);

/**
 * `ZaaktypeBlueprint` resolves each blueprint slot: it prefers the configured
 * mapping selector and otherwise falls back to the original heuristic. These
 * tests pin both branches per slot so an empty mapping keeps the legacy
 * behaviour exactly.
 */

use App\Models\MunicipalityZaaktypeMapping;
use App\Services\Zgw\ZaaktypeBlueprint;

function mapping(array $attributes = []): MunicipalityZaaktypeMapping
{
    return new MunicipalityZaaktypeMapping($attributes);
}

describe('eigenschapNaam', function () {
    test('falls back to the logical key when no mapping', function () {
        expect(ZaaktypeBlueprint::eigenschapNaam(null, 'start_evenement'))->toBe('start_evenement');
    });

    test('falls back to the logical key when the map lacks the key', function () {
        expect(ZaaktypeBlueprint::eigenschapNaam(mapping(['eigenschap_map' => ['eind_evenement' => 'Eind']]), 'start_evenement'))
            ->toBe('start_evenement');
    });

    test('translates via the eigenschap_map when present', function () {
        expect(ZaaktypeBlueprint::eigenschapNaam(mapping(['eigenschap_map' => ['start_evenement' => 'EvenementStartDatum']]), 'start_evenement'))
            ->toBe('EvenementStartDatum');
    });
});

describe('initialStatustype', function () {
    $statustypen = [
        ['url' => 'st/2', 'omschrijving' => 'In behandeling', 'volgnummer' => 2],
        ['url' => 'st/1', 'omschrijving' => 'Ontvangen', 'volgnummer' => 1],
    ];

    test('picks lowest volgnummer without a mapping', function () use ($statustypen) {
        expect(ZaaktypeBlueprint::initialStatustype(null, $statustypen)['url'])->toBe('st/1');
    });

    test('picks the mapped omschrijving when set', function () use ($statustypen) {
        expect(ZaaktypeBlueprint::initialStatustype(mapping(['initial_statustype' => 'In behandeling']), $statustypen)['url'])
            ->toBe('st/2');
    });

    test('falls back to heuristic when the mapped omschrijving is absent', function () use ($statustypen) {
        expect(ZaaktypeBlueprint::initialStatustype(mapping(['initial_statustype' => 'Onbekend']), $statustypen)['url'])
            ->toBe('st/1');
    });
});

describe('eindStatustype', function () {
    $statustypen = [
        ['url' => 'st/1', 'omschrijving' => 'Ontvangen', 'isEindstatus' => false],
        ['url' => 'st/9', 'omschrijving' => 'Afgehandeld', 'isEindstatus' => true],
    ];

    test('picks isEindstatus without a mapping', function () use ($statustypen) {
        expect(ZaaktypeBlueprint::eindStatustype(null, $statustypen)['url'])->toBe('st/9');
    });

    test('picks the mapped omschrijving when set', function () use ($statustypen) {
        expect(ZaaktypeBlueprint::eindStatustype(mapping(['eind_statustype' => 'Ontvangen']), $statustypen)['url'])
            ->toBe('st/1');
    });
});

describe('initiatorRoltype', function () {
    $roltypen = [
        ['url' => 'rt/1', 'omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ['url' => 'rt/2', 'omschrijving' => 'Behandelaar', 'omschrijvingGeneriek' => 'behandelaar'],
    ];

    test('matches omschrijvingGeneriek=initiator without a mapping', function () use ($roltypen) {
        expect(ZaaktypeBlueprint::initiatorRoltype(null, $roltypen)['url'])->toBe('rt/1');
    });

    test('matches the mapped omschrijving when set', function () use ($roltypen) {
        expect(ZaaktypeBlueprint::initiatorRoltype(mapping(['initiator_roltype' => 'Behandelaar']), $roltypen)['url'])
            ->toBe('rt/2');
    });
});

describe('ingetrokkenResultaattype', function () {
    $resultaattypen = [
        ['url' => 'rt/1', 'omschrijving' => 'Verleend', 'omschrijvingGeneriek' => 'toegekend'],
        ['url' => 'rt/2', 'omschrijving' => 'Ingetrokken op verzoek', 'omschrijvingGeneriek' => 'Ingetrokken'],
    ];

    test('matches omschrijvingGeneriek=Ingetrokken without a mapping', function () use ($resultaattypen) {
        expect(ZaaktypeBlueprint::ingetrokkenResultaattype(null, $resultaattypen)['url'])->toBe('rt/2');
    });

    test('matches the mapped omschrijving when set', function () use ($resultaattypen) {
        expect(ZaaktypeBlueprint::ingetrokkenResultaattype(mapping(['ingetrokken_resultaattype' => 'Ingetrokken op verzoek']), $resultaattypen)['url'])
            ->toBe('rt/2');
    });
});

describe('bijlageInformatieobjecttype', function () {
    $types = collect([
        (object) ['url' => 'iot/1', 'omschrijving' => 'Besluit'],
        (object) ['url' => 'iot/2', 'omschrijving' => 'Bijlage aanvraag'],
    ]);

    test('prefers a "bijlage" omschrijving without a mapping', function () use ($types) {
        expect(ZaaktypeBlueprint::bijlageInformatieobjecttype(null, $types)->url)->toBe('iot/2');
    });

    test('falls back to the first type when the bijlage heuristic is disabled', function () use ($types) {
        expect(ZaaktypeBlueprint::bijlageInformatieobjecttype(null, $types, matchBijlageInOmschrijving: false)->url)->toBe('iot/1');
    });

    test('uses the mapped omschrijving when set', function () use ($types) {
        expect(ZaaktypeBlueprint::bijlageInformatieobjecttype(mapping(['bijlage_informatieobjecttype' => 'Besluit']), $types)->url)
            ->toBe('iot/1');
    });
});
