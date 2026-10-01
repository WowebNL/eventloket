<?php

declare(strict_types=1);

/**
 * The places that show submission deadlines used to ask for A, B and C by
 * name. This class is now the only one that knows which deadlines exist and
 * which of them belong to the current path. The tests below cover that
 * filter, because it is the difference between someone filing a report
 * seeing their own deadline and seeing three classifications that are not
 * about them.
 */

use App\EventForm\State\FormState;
use App\EventForm\Support\Indieningstermijnen;

function termijnenState(array $values = [], array $gemeenteVariabelen = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => array_merge([
            'indieningstermijn_a' => 8,
            'indieningstermijn_b' => 13,
            'indieningstermijn_c' => 23,
            'indieningstermijn_melding' => 4,
        ], $gemeenteVariabelen),
    ], $values));
}

test('zolang de scan loopt zijn alle ingestelde termijnen in beeld', function () {
    // The date step comes before the scan, so at that point it is not yet
    // known whether this becomes a permit application or a report.
    $termijnen = Indieningstermijnen::forState(termijnenState());

    expect(array_column($termijnen, 'key'))->toBe([
        'indieningstermijn_a',
        'indieningstermijn_b',
        'indieningstermijn_c',
        'indieningstermijn_melding',
    ]);
});

test('een melder ziet alleen de melding-termijn', function () {
    $termijnen = Indieningstermijnen::forState(termijnenState([
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]));

    expect($termijnen)->toHaveCount(1)
        ->and($termijnen[0]['key'])->toBe('indieningstermijn_melding')
        ->and($termijnen[0]['classificatie'])->toBeNull()
        ->and($termijnen[0]['weeks'])->toBe(4);
});

test('een vergunningaanvrager ziet alleen de klassetermijnen', function () {
    $termijnen = Indieningstermijnen::forState(termijnenState([
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]));

    expect(array_column($termijnen, 'classificatie'))->toBe(['A', 'B', 'C']);
});

test('het filter werkt ook met het nieuwe vragensysteem', function () {
    $gemeenteVariabelen = [
        'use_new_report_questions' => true,
        'report_questions' => [['id' => 1, 'order' => 1, 'question' => 'Vraag 1']],
    ];

    $melder = Indieningstermijnen::forState(
        termijnenState(['reportQuestion_1' => 'Ja'], $gemeenteVariabelen),
    );
    $vergunning = Indieningstermijnen::forState(
        termijnenState(['reportQuestion_1' => 'Nee'], $gemeenteVariabelen),
    );

    expect(array_column($melder, 'key'))->toBe(['indieningstermijn_melding'])
        ->and(array_column($vergunning, 'classificatie'))->toBe(['A', 'B', 'C']);
});

test('een termijn van nul telt als niet ingesteld', function () {
    // Zero is the value the variable is seeded with, so it means "nothing
    // filled in yet" rather than "zero weeks".
    $termijnen = Indieningstermijnen::forState(termijnenState(gemeenteVariabelen: [
        'indieningstermijn_b' => 0,
        'indieningstermijn_melding' => 0,
    ]));

    expect(array_column($termijnen, 'key'))->toBe([
        'indieningstermijn_a',
        'indieningstermijn_c',
    ]);
});

test('de klassetermijnen staan los van het pad op te vragen', function () {
    // The risk-scan step is about the classifications by definition, even
    // while the scan itself has no outcome yet.
    $termijnen = Indieningstermijnen::classificaties(termijnenState());

    expect(array_column($termijnen, 'weeks'))->toBe([8, 13, 23])
        ->and(array_column($termijnen, 'omschrijving'))->toBe(['klein', 'middelgroot', 'groot']);
});
