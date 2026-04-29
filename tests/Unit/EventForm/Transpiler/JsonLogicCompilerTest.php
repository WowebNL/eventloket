<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsonLogicCompiler;

/**
 * Helper: compile het JsonLogic-patroon naar een PHP-expressie en evalueer
 * die tegen een FormState. Zo testen we de runtime-correctheid zonder dat
 * we gevoelig zijn voor cosmetische verschillen in de gegenereerde string.
 */
function evalLogic(mixed $expr, ?FormState $state = null): mixed
{
    $state = $state ?? FormState::empty();
    $php = (new JsonLogicCompiler)->compile($expr);
    /** @var callable(FormState): mixed $fn */
    $fn = eval("return fn(\App\EventForm\State\FormState \$s) => {$php};");

    return $fn($state);
}

describe('JsonLogicCompiler literals', function () {
    test('integer literal', function () {
        expect(evalLogic(42))->toBe(42);
    });

    test('string literal', function () {
        expect(evalLogic('hello'))->toBe('hello');
    });

    test('boolean literal', function () {
        expect(evalLogic(true))->toBeTrue()
            ->and(evalLogic(false))->toBeFalse();
    });

    test('null literal', function () {
        expect(evalLogic(null))->toBeNull();
    });
});

describe('JsonLogicCompiler var', function () {
    test('simple var lookup', function () {
        $state = FormState::empty();
        $state->setField('soortEvenement', 'Markt of braderie');

        expect(evalLogic(['var' => 'soortEvenement'], $state))->toBe('Markt of braderie');
    });

    test('nested var lookup with dot notation', function () {
        $state = FormState::empty();
        $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 500]);

        expect(evalLogic(['var' => 'gemeenteVariabelen.aanwezigen'], $state))->toBe(500);
    });

    test('selectboxes-member var lookup', function () {
        $state = FormState::empty();
        $state->setField('waarVindtHetEvenementPlaats', ['buiten' => true]);

        expect(evalLogic(['var' => 'waarVindtHetEvenementPlaats.buiten'], $state))->toBeTrue();
    });

    test('missing var returns null', function () {
        expect(evalLogic(['var' => 'doesNotExist']))->toBeNull();
    });
});

describe('JsonLogicCompiler comparisons', function () {
    test('equality', function () {
        $state = FormState::empty();
        $state->setField('x', 'a');
        expect(evalLogic(['==' => [['var' => 'x'], 'a']], $state))->toBeTrue()
            ->and(evalLogic(['==' => [['var' => 'x'], 'b']], $state))->toBeFalse();
    });

    test('inequality', function () {
        $state = FormState::empty();
        $state->setField('x', 'a');
        expect(evalLogic(['!=' => [['var' => 'x'], 'b']], $state))->toBeTrue()
            ->and(evalLogic(['!=' => [['var' => 'x'], 'a']], $state))->toBeFalse();
    });

    test('greater than or equal', function () {
        $state = FormState::empty();
        $state->setField('count', 5);
        expect(evalLogic(['>=' => [['var' => 'count'], 3]], $state))->toBeTrue()
            ->and(evalLogic(['>=' => [['var' => 'count'], 10]], $state))->toBeFalse();
    });
});

describe('JsonLogicCompiler logical', function () {
    test('and with true/false arms', function () {
        expect(evalLogic(['and' => [true, true]]))->toBeTrue()
            ->and(evalLogic(['and' => [true, false]]))->toBeFalse();
    });

    test('or with true/false arms', function () {
        expect(evalLogic(['or' => [false, true]]))->toBeTrue()
            ->and(evalLogic(['or' => [false, false]]))->toBeFalse();
    });

    test('double-bang converts to boolean', function () {
        expect(evalLogic(['!!' => [['var' => 'missing']]]))->toBeFalse()
            ->and(evalLogic(['!!' => ['non-empty']]))->toBeTrue();
    });
});

describe('JsonLogicCompiler if', function () {
    test('ternary with three arms picks then-arm on truthy', function () {
        expect(evalLogic(['if' => [true, 'yes', 'no']]))->toBe('yes')
            ->and(evalLogic(['if' => [false, 'yes', 'no']]))->toBe('no');
    });
});

describe('JsonLogicCompiler missing', function () {
    test('missing returns list of absent keys', function () {
        $state = FormState::empty();
        $state->setField('a', 1);

        expect(evalLogic(['missing' => ['a', 'b']], $state))->toBe(['b']);
    });
});

describe('JsonLogicCompiler arithmetic', function () {
    test('plus operator sums numbers', function () {
        // De compiler cast naar float omdat scores in de risicoscan floats zijn.
        expect(evalLogic(['+' => [1, 2]]))->toBe(3.0);
    });
});

describe('JsonLogicCompiler reduce (count pattern)', function () {
    test('reduce with accumulator+1 compiles to count()', function () {
        // Setup via `inGemeentenResponse.all.items` omdat
        // `evenementInGemeentenNamen` sinds de FormDerivedState-migratie
        // gemigreerd is naar pure-functioneel — direct `setVariable`
        // werkt niet meer (FormDerivedState overruled).
        $state = new FormState(values: [
            'inGemeentenResponse' => ['all' => ['items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ['brk_identification' => 'GM0928', 'name' => 'Kerkrade'],
            ]]],
        ]);

        $expr = [
            'reduce' => [
                ['var' => 'evenementInGemeentenNamen'],
                ['+' => [1, ['var' => 'accumulator']]],
                0,
            ],
        ];
        expect(evalLogic($expr, $state))->toBe(3);
    });

    test('reduce of missing variable yields 0 (empty array)', function () {
        $expr = [
            'reduce' => [
                ['var' => 'ietsWatNietBestaat'],
                ['+' => [1, ['var' => 'accumulator']]],
                0,
            ],
        ];
        expect(evalLogic($expr))->toBe(0);
    });
});

describe('JsonLogicCompiler variadic plus', function () {
    test('plus with three+ operands sums them all (risicoscan)', function () {
        $state = FormState::empty();
        $state->setField('a', 1.5);
        $state->setField('b', 0.5);
        $state->setField('c', 1);

        $expr = ['+' => [['var' => 'a'], ['var' => 'b'], ['var' => 'c']]];
        expect(evalLogic($expr, $state))->toBe(3.0);
    });
});

describe('JsonLogicCompiler less-than-or-equal', function () {
    test('<= with numbers', function () {
        expect(evalLogic(['<=' => [3, 5]]))->toBeTrue()
            ->and(evalLogic(['<=' => [7, 5]]))->toBeFalse();
    });
});

describe('JsonLogicCompiler cat', function () {
    test('concatenates strings', function () {
        expect(evalLogic(['cat' => ['gemeenten.', 'GM0882']]))->toBe('gemeenten.GM0882');
    });

    test('concatenates with var lookups', function () {
        $state = FormState::empty();
        $state->setField('userSelectGemeente', 'GM0882');

        expect(evalLogic(['cat' => ['gemeenten.', ['var' => 'userSelectGemeente']]], $state))
            ->toBe('gemeenten.GM0882');
    });
});

describe('JsonLogicCompiler dynamic var', function () {
    test('var with cat-expression resolves the runtime-built path', function () {
        $state = FormState::empty();
        $state->setField('userSelectGemeente', 'GM0882');
        $state->setVariable('gemeenten', ['GM0882' => ['name' => 'Maastricht']]);

        $expr = ['var' => ['cat' => ['gemeenten.', ['var' => 'userSelectGemeente']]]];
        expect(evalLogic($expr, $state))->toBe(['name' => 'Maastricht']);
    });
});

describe('JsonLogicCompiler map', function () {
    test('map extracts fields from items via inner var', function () {
        $state = FormState::empty();
        $state->setVariable('items', [
            ['name' => 'Maastricht', 'brk_identification' => 'GM0882'],
            ['name' => 'Heerlen', 'brk_identification' => 'GM0917'],
        ]);

        $expr = ['map' => [['var' => 'items'], ['var' => 'name']]];
        expect(evalLogic($expr, $state))->toBe(['Maastricht', 'Heerlen']);
    });

    test('map over missing variable yields empty list', function () {
        $expr = ['map' => [['var' => 'doesNotExist'], ['var' => 'name']]];
        expect(evalLogic($expr))->toBe([]);
    });
});

describe('JsonLogicCompiler merge', function () {
    test('merge flattens multiple arrays', function () {
        $expr = ['merge' => [['a', 'b'], ['c']]];
        expect(evalLogic($expr))->toBe(['a', 'b', 'c']);
    });
});

describe('JsonLogicCompiler nested combinations', function () {
    test('and of multiple equalities', function () {
        $state = FormState::empty();
        $state->setField('a', 'x');
        $state->setField('b', 'y');
        $expr = [
            'and' => [
                ['==' => [['var' => 'a'], 'x']],
                ['==' => [['var' => 'b'], 'y']],
            ],
        ];
        expect(evalLogic($expr, $state))->toBeTrue();
    });

    test('truthy-check combined with not-equal-to-None', function () {
        $state = FormState::empty();
        $state->setField('adresVanDeGebouwEn', ['naamVanDeLocatieGebouw' => 'Stadhuis']);

        $expr = [
            'and' => [
                ['!!' => [['var' => 'adresVanDeGebouwEn']]],
                ['!=' => [['var' => 'adresVanDeGebouwEn'], 'None']],
            ],
        ];
        expect(evalLogic($expr, $state))->toBeTrue();
    });

    test('reduce result compared with >=', function () {
        $state = new FormState(values: [
            'inGemeentenResponse' => ['all' => ['items' => [
                ['brk_identification' => 'X', 'name' => 'A'],
                ['brk_identification' => 'Y', 'name' => 'B'],
            ]]],
        ]);

        $expr = [
            '>=' => [
                [
                    'reduce' => [
                        ['var' => 'evenementInGemeentenNamen'],
                        ['+' => [1, ['var' => 'accumulator']]],
                        0,
                    ],
                ],
                2,
            ],
        ];
        expect(evalLogic($expr, $state))->toBeTrue();
    });
});
