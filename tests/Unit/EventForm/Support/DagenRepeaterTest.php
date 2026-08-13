<?php

declare(strict_types=1);

/**
 * The per-day rows an organiser fills in for a multi-day period, and the way
 * they travel to and from the stored day blocks on the zaak.
 */

use App\EventForm\Support\DagenRepeater;
use Carbon\CarbonImmutable;

test('een eendaagse periode levert geen dagregels op', function () {
    expect(DagenRepeater::sync('2026-07-04 10:00', '2026-07-04 18:00'))->toBe([]);
    // Not even when the end time falls in the small hours.
    expect(DagenRepeater::sync('2026-07-04 16:00', '2026-07-05 02:00'))->toBe([]);
});

test('een meerdaagse periode levert één regel per evenementdag op', function () {
    $rijen = DagenRepeater::sync('2026-07-04 16:00', '2026-07-07 02:00');

    expect(array_keys($rijen))->toBe(['2026-07-04', '2026-07-05', '2026-07-06']);
});

test('de eerste starttijd en de laatste eindtijd spiegelen de envelope', function () {
    $rijen = DagenRepeater::sync('2026-07-04 16:00', '2026-07-06 23:30');

    expect($rijen['2026-07-04']['startTijd'])->toBe('16:00');
    expect($rijen['2026-07-06']['eindTijd'])->toBe('23:30');
    // The remaining times stay empty until the organiser fills them in.
    expect($rijen['2026-07-04']['eindTijd'])->toBeNull();
    expect($rijen['2026-07-05']['startTijd'])->toBeNull();
});

test('bestaande tijden blijven behouden als de datumspan verandert', function () {
    $bestaand = DagenRepeater::sync('2026-07-04 16:00', '2026-07-06 23:00');
    $bestaand['2026-07-05']['startTijd'] = '14:00';
    $bestaand['2026-07-05']['eindTijd'] = '23:00';

    // A day is added at the end.
    $nieuw = DagenRepeater::sync('2026-07-04 16:00', '2026-07-07 23:00', $bestaand);

    expect(array_keys($nieuw))->toBe(['2026-07-04', '2026-07-05', '2026-07-06', '2026-07-07']);
    expect($nieuw['2026-07-05']['startTijd'])->toBe('14:00');
    expect($nieuw['2026-07-05']['eindTijd'])->toBe('23:00');
    // The last day has shifted, so that one now mirrors the envelope.
    expect($nieuw['2026-07-07']['eindTijd'])->toBe('23:00');
});

test('rijen met een uuid-sleutel worden herkend aan hun eigen datum', function () {
    $bestaand = [
        '0198-abcd' => ['datum' => '2026-07-05', 'startTijd' => '14:00', 'eindTijd' => '23:00'],
    ];

    $rijen = DagenRepeater::sync('2026-07-04 16:00', '2026-07-06 23:00', $bestaand);

    expect($rijen['2026-07-05']['startTijd'])->toBe('14:00');
});

test('dagregels worden omgezet naar dagblokken met doorrollende eindtijden', function () {
    $blokken = DagenRepeater::naarReferenceData([
        '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
        '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '16:00', 'eindTijd' => '23:00'],
    ]);

    expect($blokken)->toHaveCount(2);
    expect($blokken[0]['datum'])->toBe('2026-07-04');
    expect(CarbonImmutable::parse($blokken[0]['eind'])->toDateTimeString())
        ->toBe('2026-07-05 02:00:00');
    expect(CarbonImmutable::parse($blokken[1]['eind'])->toDateTimeString())
        ->toBe('2026-07-05 23:00:00');
});

test('onvolledige dagregels leveren geen half dagblok op', function () {
    $blokken = DagenRepeater::naarReferenceData([
        '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => null],
        '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '16:00', 'eindTijd' => '23:00'],
    ]);

    expect($blokken)->toHaveCount(1);
    expect($blokken[0]['datum'])->toBe('2026-07-05');
});

test('opgeslagen dagblokken zijn terug te lezen als dagregels voor een kopie', function () {
    $blokken = DagenRepeater::naarReferenceData([
        '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
    ]);

    $rijen = DagenRepeater::uitReferenceData($blokken);

    expect($rijen['2026-07-04'])->toBe([
        'datum' => '2026-07-04',
        'startTijd' => '16:00',
        'eindTijd' => '02:00',
    ]);
});

test('dagblokken renderen als leesbare tabelrijen met een nachtmarkering', function () {
    $blokken = DagenRepeater::naarReferenceData([
        '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
        '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '10:00', 'eindTijd' => '18:00'],
    ]);

    $rijen = DagenRepeater::alsTabelRijen($blokken);

    expect($rijen[0]['start'])->toBe('16:00');
    // A roll-over into the next morning is made explicit.
    expect($rijen[0]['eind'])->toContain('02:00');
    expect($rijen[0]['eind'])->toContain('5');
    // A block inside a single day gets no marker.
    expect($rijen[1]['eind'])->toBe('18:00');
});

test('ontbrekende of onbruikbare dagdata levert lege lijsten op', function () {
    expect(DagenRepeater::uitReferenceData(null))->toBe([]);
    expect(DagenRepeater::alsTabelRijen(null))->toBe([]);
    expect(DagenRepeater::alsTabelRijen(['onzin']))->toBe([]);
});

test('de eerste en laatste dag van de envelope zijn herkenbaar', function () {
    expect(DagenRepeater::isEersteDag('2026-07-04', '2026-07-04 16:00'))->toBeTrue();
    expect(DagenRepeater::isEersteDag('2026-07-05', '2026-07-04 16:00'))->toBeFalse();

    // The last day is the effective end day, not the calendar day of the end moment.
    expect(DagenRepeater::isLaatsteDag('2026-07-06', '2026-07-04 16:00', '2026-07-07 02:00'))->toBeTrue();
    expect(DagenRepeater::isLaatsteDag('2026-07-07', '2026-07-04 16:00', '2026-07-07 02:00'))->toBeFalse();
});
