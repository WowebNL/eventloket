<?php

use App\Support\RisicoClassificatie;

test('the manual classification is stored as 0 and presented as M', function () {
    expect(RisicoClassificatie::options())->toBe([
        '0' => 'M',
        'A' => 'A',
        'B' => 'B',
        'C' => 'C',
    ]);

    expect(RisicoClassificatie::label('0'))->toBe('M');
});

test('the calculated classifications keep their own label', function (string $value) {
    expect(RisicoClassificatie::label($value))->toBe($value);
})->with(['A', 'B', 'C']);

test('an unknown or missing classification is returned unchanged', function () {
    expect(RisicoClassificatie::label('D'))->toBe('D')
        ->and(RisicoClassificatie::label(''))->toBe('')
        ->and(RisicoClassificatie::label(null))->toBeNull();
});
