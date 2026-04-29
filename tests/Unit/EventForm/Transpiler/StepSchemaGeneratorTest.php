<?php

declare(strict_types=1);

use App\EventForm\Transpiler\StepSchemaGenerator;

function generateStep(array $step): string
{
    $generator = new StepSchemaGenerator;

    return $generator->generate($step)->fileContent;
}

describe('StepSchemaGenerator class structure', function () {
    test('generated class has Step::make with step name', function () {
        $step = [
            'uuid' => 'step-uuid-1',
            'slug' => 'contactgegevens',
            'name' => 'Contactgegevens',
            'index' => 0,
            'configuration' => ['components' => []],
        ];

        $content = generateStep($step);

        expect($content)->toContain("namespace App\\EventForm\\Schema\\Steps")
            ->and($content)->toContain('class ContactgegevensStep')
            ->and($content)->toContain("Step::make('Contactgegevens')")
            ->and($content)->toContain('@openforms-step-uuid step-uuid-1');
    });

    test('class name is derived from step slug as PascalCase', function () {
        $step = [
            'uuid' => 'x',
            'slug' => 'vergunningaanvraag-vervolgvragen',
            'name' => 'Vergunningaanvraag: kenmerken',
            'configuration' => ['components' => []],
        ];

        $generator = new StepSchemaGenerator;
        $generated = $generator->generate($step);

        expect($generated->className)->toBe('VergunningaanvraagVervolgvragenStep');
    });
});

describe('StepSchemaGenerator field emission', function () {
    test('textfield emits TextInput', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => [
                'components' => [
                    [
                        'key' => 'watIsUwVoornaam',
                        'type' => 'textfield',
                        'label' => 'Wat is uw voornaam?',
                        'validate' => ['required' => true, 'maxLength' => 50],
                    ],
                ],
            ],
        ];

        $content = generateStep($step);

        expect($content)->toContain("TextInput::make('watIsUwVoornaam')")
            ->and($content)->toContain("->label('Wat is uw voornaam?')")
            ->and($content)->toContain('->required()')
            ->and($content)->toContain('->maxLength(50)');
    });

    test('textarea emits Textarea', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => [
                'components' => [
                    ['key' => 'beschrijving', 'type' => 'textarea', 'label' => 'Beschrijving'],
                ],
            ],
        ];

        expect(generateStep($step))->toContain("Textarea::make('beschrijving')");
    });

    test('email emits TextInput with email()', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'emailveld', 'type' => 'email', 'label' => 'E-mail'],
            ]],
        ];

        $content = generateStep($step);
        expect($content)->toContain("TextInput::make('emailveld')")
            ->and($content)->toContain('->email()');
    });

    test('number emits TextInput numeric', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'aantal', 'type' => 'number', 'label' => 'Aantal'],
            ]],
        ];

        expect(generateStep($step))->toContain('->numeric()');
    });

    test('radio emits Radio with options', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'waarvoorWiltUEventloketGebruiken',
                    'type' => 'radio',
                    'label' => 'Waarvoor?',
                    'values' => [
                        ['value' => 'evenement', 'label' => 'Voor een evenement'],
                        ['value' => 'vooraankondiging', 'label' => 'Voor een vooraankondiging'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("Radio::make('waarvoorWiltUEventloketGebruiken')")
            ->and($content)->toContain("'evenement' => 'Voor een evenement'")
            ->and($content)->toContain("'vooraankondiging' => 'Voor een vooraankondiging'");
    });

    test('selectboxes emits CheckboxList', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'extras',
                    'type' => 'selectboxes',
                    'label' => 'Extras',
                    'values' => [
                        ['value' => 'a', 'label' => 'Optie A'],
                        ['value' => 'b', 'label' => 'Optie B'],
                    ],
                ],
            ]],
        ];

        expect(generateStep($step))->toContain("CheckboxList::make('extras')");
    });

    test('datetime emits DateTimePicker', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'start', 'type' => 'datetime', 'label' => 'Start'],
            ]],
        ];

        expect(generateStep($step))->toContain("DateTimePicker::make('start')");
    });

    test('file emits FileUpload', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'bijlage', 'type' => 'file', 'label' => 'Bijlage'],
            ]],
        ];

        expect(generateStep($step))->toContain("FileUpload::make('bijlage')");
    });

    test('map emits dotswan Map field with default location + zoom + GeoMan', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'locatie', 'type' => 'map', 'label' => 'Locatie'],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("Map::make('locatie')")
            ->and($content)->toContain('->defaultLocation(')
            ->and($content)->toContain('->zoom(')
            ->and($content)->toContain('->geoMan(true)')
            ->and($content)->toContain('->geoManEditable(true)');
    });

    test('map with polygon interaction only allows polygon drawing', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'buitenLocatie',
                    'type' => 'map',
                    'label' => 'Buitenlocatie',
                    'interactions' => ['marker' => false, 'polygon' => true, 'polyline' => false],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('->drawPolygon(true)')
            ->and($content)->toContain('->drawPolyline(false)')
            ->and($content)->toContain('->drawMarker(false)');
    });

    test('map with polyline interaction only allows line drawing (routes)', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'routeVanHetEvenement',
                    'type' => 'map',
                    'label' => 'Route',
                    'interactions' => ['marker' => false, 'polygon' => false, 'polyline' => true],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('->drawPolyline(true)')
            ->and($content)->toContain('->drawPolygon(false)')
            ->and($content)->toContain('->drawMarker(false)');
    });

    test('map without interactions config defaults to marker-only', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'punt', 'type' => 'map', 'label' => 'Punt'],
            ]],
        ];

        expect(generateStep($step))->toContain('->drawMarker(true)');
    });
});

describe('StepSchemaGenerator nesting', function () {
    test('empty fieldset (no children) is skipped', function () {
        // OF gebruikt lege fieldsets als section-header; in Filament rendert
        // dat als een grote lege box en dat is puur visuele ruis.
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'leegVakje',
                    'type' => 'fieldset',
                    'label' => 'Alleen een header',
                    'components' => [],
                ],
            ]],
        ];

        $content = generateStep($step);

        // Geen Fieldset::make call voor deze lege fieldset.
        expect($content)->not->toContain('Fieldset::make')
            ->and($content)->not->toContain("'Alleen een header'");
    });

    test('fieldset wraps its children in Fieldset::make', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'persoon',
                    'type' => 'fieldset',
                    'label' => 'Persoon',
                    'components' => [
                        ['key' => 'naam', 'type' => 'textfield', 'label' => 'Naam'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("Fieldset::make('Persoon')")
            ->and($content)->toContain("TextInput::make('naam')");
    });

    test('columns wraps its children in Grid::make(1) ongeacht het OF-aantal kolommen', function () {
        // Sinds G.1 forceren we 1-koloms layout omdat 2-koloms in onze
        // 700px-content-area onleesbaar smal werd. De velden zelf zitten
        // wel gewoon allemaal in de wrapper.
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'cols',
                    'type' => 'columns',
                    'columns' => [
                        ['components' => [['key' => 'a', 'type' => 'textfield', 'label' => 'A']]],
                        ['components' => [['key' => 'b', 'type' => 'textfield', 'label' => 'B']]],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('Grid::make(1)')
            ->and($content)->not->toContain('Grid::make(2)')
            ->and($content)->toContain("TextInput::make('a')")
            ->and($content)->toContain("TextInput::make('b')");
    });

    test('editgrid emits Repeater with inner schema', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'tenten',
                    'type' => 'editgrid',
                    'label' => 'Welke tenten',
                    'components' => [
                        ['key' => 'tentnummer', 'type' => 'textfield', 'label' => 'Tent nr'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("Repeater::make('tenten')")
            ->and($content)->toContain("TextInput::make('tentnummer')");
    });
});

describe('StepSchemaGenerator variable-backed options', function () {
    test('select with openForms.dataSrc=variable gets options from variable initial_value', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'soortEvenement',
                    'type' => 'select',
                    'label' => 'Soort',
                    'values' => [['value' => '', 'label' => '']],
                    'openForms' => ['dataSrc' => 'variable', 'itemsExpression' => ['var' => 'evenementTypen']],
                ],
            ]],
        ];

        $content = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withVariableInitialValues([
                'evenementTypen' => ['Markt of braderie', 'Muziekevenement', 'Kermis'],
            ])
            ->generate($step)->fileContent;

        expect($content)->toContain("'Markt of braderie' => 'Markt of braderie'")
            ->and($content)->toContain("'Muziekevenement' => 'Muziekevenement'");
    });

    test('radio backed by jaNeeLijst gets Ja/Nee options', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'heeftOpbouw',
                    'type' => 'radio',
                    'label' => 'Opbouw?',
                    'values' => [['value' => '', 'label' => '']],
                    'openForms' => ['dataSrc' => 'variable', 'itemsExpression' => ['var' => 'jaNeeLijst']],
                ],
            ]],
        ];

        $content = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withVariableInitialValues(['jaNeeLijst' => ['Ja', 'Nee']])
            ->generate($step)->fileContent;

        expect($content)->toContain("'Ja' => 'Ja'")
            ->and($content)->toContain("'Nee' => 'Nee'");
    });

    test('checkboxes backed by a code-pair list emit code as key + label as value', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'voorwerpen',
                    'type' => 'selectboxes',
                    'label' => 'Voorwerpen',
                    'values' => [['value' => '', 'label' => '']],
                    'openForms' => ['dataSrc' => 'variable', 'itemsExpression' => ['var' => 'voorwerpenLijst']],
                ],
            ]],
        ];

        $content = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withVariableInitialValues([
                'voorwerpenLijst' => [
                    ['A23', 'Verkooppunten toegangskaarten'],
                    ['A24', 'Verkooppunten munten'],
                ],
            ])
            ->generate($step)->fileContent;

        expect($content)->toContain("'A23' => 'Verkooppunten toegangskaarten'")
            ->and($content)->toContain("'A24' => 'Verkooppunten munten'");
    });
});

describe('StepSchemaGenerator label interpolation', function () {
    test('labels with {{ var }} get a closure that delegates to LabelRenderer', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'soortEvenement',
                    'type' => 'select',
                    'label' => 'Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?',
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('->label(fn')
            ->and($content)->toContain('LabelRenderer')
            ->and($content)->toContain('{{ watIsDeNaamVanHetEvenementVergunning }}');
    });

    test('labels without placeholders stay as plain strings', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'voornaam', 'type' => 'textfield', 'label' => 'Wat is uw voornaam?'],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("->label('Wat is uw voornaam?')")
            ->and($content)->not->toContain('LabelRenderer');
    });
});

describe('StepSchemaGenerator live triggers', function () {
    test('a field used as conditional.when trigger gets ->live()', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'soortEvenement', 'type' => 'select', 'label' => 'Soort'],
                [
                    'key' => 'omschrijf',
                    'type' => 'textarea',
                    'label' => 'Omschrijf',
                    'conditional' => ['show' => true, 'when' => 'soortEvenement', 'eq' => 'Anders'],
                ],
            ]],
        ];

        $generator = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withTriggerKeys(['soortEvenement']);
        $content = $generator->generate($step)->fileContent;

        // Het trigger-veld (select) moet ->live() hebben zodat Filament
        // de visibility-closure bij state-change her-evalueert.
        expect($content)->toContain("Select::make('soortEvenement')")
            ->and($content)->toContain('->live()');
    });

    test('a selectboxes used as trigger also gets ->live()', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'waarVindtHetEvenementPlaats',
                    'type' => 'selectboxes',
                    'label' => 'Waar',
                    'values' => [['value' => 'buiten', 'label' => 'Buiten']],
                ],
            ]],
        ];

        $generator = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withTriggerKeys(['waarVindtHetEvenementPlaats']);

        expect($generator->generate($step)->fileContent)->toContain('->live()');
    });

    test('a non-trigger field does not get ->live()', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'opmerking', 'type' => 'textarea', 'label' => 'Opmerking'],
            ]],
        ];

        $generator = (new \App\EventForm\Transpiler\StepSchemaGenerator)
            ->withTriggerKeys([]);

        expect($generator->generate($step)->fileContent)->not->toContain('->live()');
    });
});

describe('StepSchemaGenerator visibility', function () {
    test('hidden:true fields emit a ->hidden(fn) closure that checks FormState + default', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'organisatieInformatie',
                    'type' => 'fieldset',
                    'label' => 'Organisatie',
                    'hidden' => true,
                    'components' => [
                        ['key' => 'dummy', 'type' => 'textfield', 'label' => 'Dummy'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('->hidden(')
            ->and($content)->toContain("isFieldHidden('organisatieInformatie')")
            // Default-hidden emit: "!== false" is genoeg — het veld is
            // verborgen tenzij een rule expliciet unhides.
            ->and($content)->toContain('!== false');
    });

    test('conditional.show=true emits closure that hides when no match', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'soortEvenement', 'type' => 'select', 'label' => 'Soort'],
                [
                    'key' => 'omschrijfHetSoortEvenement',
                    'type' => 'textarea',
                    'label' => 'Omschrijf',
                    'conditional' => ['show' => true, 'when' => 'soortEvenement', 'eq' => 'Anders'],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("\$get('soortEvenement')")
            ->and($content)->toContain("'Anders'")
            ->and($content)->toContain('->hidden(');
    });

    test('conditional.show=false (verberg als) hides when match', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'soortEvenement', 'type' => 'select', 'label' => 'Soort'],
                [
                    'key' => 'omschrijfHetSoortEvenement',
                    'type' => 'textarea',
                    'label' => 'Omschrijf',
                    'conditional' => ['show' => false, 'when' => 'soortEvenement', 'eq' => 'Anders'],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain('->hidden(')
            ->and($content)->toContain("'Anders'");
    });

    test('conditional on selectboxes-target uses in_array for Filament state', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'waarVindtHetEvenementPlaats',
                    'type' => 'selectboxes',
                    'label' => 'Waar',
                    'values' => [['value' => 'buiten', 'label' => 'Buiten']],
                ],
                [
                    'key' => 'buitenFieldset',
                    'type' => 'fieldset',
                    'label' => 'Buiten-vraag',
                    'conditional' => ['show' => true, 'when' => 'waarVindtHetEvenementPlaats', 'eq' => 'buiten'],
                    'components' => [
                        ['key' => 'dummy', 'type' => 'textfield', 'label' => 'Dummy'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        // Filament CheckboxList slaat selectboxes op als array van values,
        // niet als object — de closure gebruikt in_array.
        expect($content)->toContain("in_array('buiten'")
            ->and($content)->toContain("\$get('waarVindtHetEvenementPlaats')");
    });

    test('default-hidden without conditional emits minimal closure (just rule-check)', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'locatieSOpKaart',
                    'type' => 'editgrid',
                    'label' => 'Locaties',
                    'hidden' => true,
                    'components' => [
                        ['key' => 'dummy', 'type' => 'textfield', 'label' => 'Dummy'],
                    ],
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("isFieldHidden('locatieSOpKaart')")
            // Geen overbodige `false || ...` patronen of dubbele if-returns.
            ->and($content)->not->toContain('false || (')
            ->and($content)->not->toContain('true || (');
    });

    test('emitted closure has no "} if" chain — elseif instead', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'x', 'type' => 'textfield', 'label' => 'X', 'hidden' => true],
            ]],
        ];

        expect(generateStep($step))->not->toContain('} if (');
    });

    test('visibility closure has a return type', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'x', 'type' => 'textfield', 'label' => 'X', 'hidden' => true],
            ]],
        ];

        expect(generateStep($step))->toContain('): bool');
    });
});

describe('StepSchemaGenerator addressNL', function () {
    test('addressNL emits AddressNL::make with key', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'adresVanHetGebouw',
                    'type' => 'addressNL',
                    'label' => 'Adres van het gebouw',
                ],
            ]],
        ];

        $content = generateStep($step);

        expect($content)->toContain("AddressNL::make('adresVanHetGebouw'")
            ->and($content)->toContain("use App\\EventForm\\Components\\AddressNL;");
    });
});

describe('StepSchemaGenerator content', function () {
    test('content blocks have inline color styles stripped (dark-mode safe)', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'info',
                    'type' => 'content',
                    'label' => 'Info',
                    'html' => '<p><span style="color:rgb(0,0,0);">Zwarte tekst</span></p>',
                ],
            ]],
        ];

        $content = generateStep($step);

        // Geen inline color meer — de tekst erft de parent-kleur, dus werkt in
        // zowel light- als dark-mode.
        expect($content)->not->toContain('color:rgb(0,0,0)')
            ->and($content)->not->toContain('color: rgb(0,0,0)')
            ->and($content)->toContain('Zwarte tekst');
    });

    test('content blocks are emitted as TextEntry with HtmlString', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'info',
                    'type' => 'content',
                    'label' => 'Info',
                    'html' => '<p>Lees eerst de instructies.</p>',
                ],
            ]],
        ];

        $content = generateStep($step);

        // Placeholder is deprecated in Filament; we gebruiken TextEntry.
        expect($content)->toContain("TextEntry::make('info')")
            ->and($content)->toContain('<p>Lees eerst de instructies.</p>');
    });
});
