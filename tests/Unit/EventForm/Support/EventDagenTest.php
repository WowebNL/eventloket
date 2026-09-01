<?php

declare(strict_types=1);

/**
 * The night-boundary rule from issue #24: an event that finishes in the small
 * hours is still a single-day event. Only when the end moment passes 06:00 of
 * the following morning do we consider it multi-day and ask for times per day.
 */

use App\EventForm\Support\EventDagen;

test('een evenement binnen één kalenderdag is niet meerdaags', function () {
    expect(EventDagen::isMeerdaags('2026-07-04 10:00', '2026-07-04 18:00'))->toBeFalse();
    expect(EventDagen::dagen('2026-07-04 10:00', '2026-07-04 18:00'))->toHaveCount(1);
});

test('een eindtijd in de nacht maakt een eendaags evenement niet meerdaags', function () {
    // 16:00 until 02:00 the next morning: one day with a late finish.
    expect(EventDagen::isMeerdaags('2026-07-04 16:00', '2026-07-05 02:00'))->toBeFalse();

    $dagen = EventDagen::dagen('2026-07-04 16:00', '2026-07-05 02:00');
    expect($dagen)->toHaveCount(1);
    expect($dagen[0]->toDateString())->toBe('2026-07-04');
});

test('de nachtgrens ligt op 06:00 en telt die grens zelf nog bij de vorige dag', function () {
    expect(EventDagen::isMeerdaags('2026-07-04 16:00', '2026-07-05 06:00'))->toBeFalse();
    expect(EventDagen::isMeerdaags('2026-07-04 16:00', '2026-07-05 06:01'))->toBeTrue();
});

test('een meerdaags evenement met nachtelijke eindtijden telt alleen de evenementdagen', function () {
    // Three days of 16:00-02:00: the last block ends on 7 July at 02:00,
    // but 7 July is not itself an event day.
    $dagen = EventDagen::dagen('2026-07-04 16:00', '2026-07-07 02:00');

    expect($dagen)->toHaveCount(3);
    expect(array_map(fn ($dag) => $dag->toDateString(), $dagen))
        ->toBe(['2026-07-04', '2026-07-05', '2026-07-06']);
});

test('een meerdaags evenement dat overdag eindigt telt de einddag mee', function () {
    $dagen = EventDagen::dagen('2026-07-04 10:00', '2026-07-06 18:00');

    expect(array_map(fn ($dag) => $dag->toDateString(), $dagen))
        ->toBe(['2026-07-04', '2026-07-05', '2026-07-06']);
});

test('ontbrekende of onleesbare momenten leveren geen dagen op', function () {
    expect(EventDagen::dagen(null, '2026-07-04 18:00'))->toBe([]);
    expect(EventDagen::dagen('2026-07-04 10:00', null))->toBe([]);
    expect(EventDagen::dagen('2026-07-04 10:00', '20267-07-04 18:00'))->toBe([]);
    expect(EventDagen::isMeerdaags(null, null))->toBeFalse();
});

test('een eind vóór de start levert nog steeds één dag op in plaats van een lege lijst', function () {
    expect(EventDagen::dagen('2026-07-04 10:00', '2026-07-02 18:00'))->toHaveCount(1);
});

test('blokEinde rolt door naar de volgende dag als de eindtijd niet later is dan de starttijd', function () {
    expect(EventDagen::blokEinde('2026-07-04', '16:00', '02:00')?->toDateTimeString())
        ->toBe('2026-07-05 02:00:00');
    expect(EventDagen::blokEinde('2026-07-04', '10:00', '18:00')?->toDateTimeString())
        ->toBe('2026-07-04 18:00:00');
    // Equal times read as a 24-hour block, not as an empty one.
    expect(EventDagen::blokEinde('2026-07-04', '16:00', '16:00')?->toDateTimeString())
        ->toBe('2026-07-05 16:00:00');
});

test('blokStart combineert de dag met de opgegeven tijd', function () {
    expect(EventDagen::blokStart('2026-07-04', '16:00')?->toDateTimeString())
        ->toBe('2026-07-04 16:00:00');
    expect(EventDagen::blokStart('2026-07-04', null))->toBeNull();
    expect(EventDagen::blokStart(null, '16:00'))->toBeNull();
});

test('een doorlopend blok mag niet voorbij de nachtgrens eindigen', function () {
    expect(EventDagen::rolloverBinnenNachtGrens('16:00', '02:00'))->toBeTrue();
    expect(EventDagen::rolloverBinnenNachtGrens('16:00', '06:00'))->toBeTrue();
    expect(EventDagen::rolloverBinnenNachtGrens('16:00', '07:00'))->toBeFalse();
    // Blocks that stay within the same day never touch the night boundary.
    expect(EventDagen::rolloverBinnenNachtGrens('10:00', '18:00'))->toBeTrue();
});
