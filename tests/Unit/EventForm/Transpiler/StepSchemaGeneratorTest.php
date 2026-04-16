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

    test('map emits dotswan Map field', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                ['key' => 'locatie', 'type' => 'map', 'label' => 'Locatie'],
            ]],
        ];

        expect(generateStep($step))->toContain("Map::make('locatie')");
    });
});

describe('StepSchemaGenerator nesting', function () {
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

    test('columns wraps its children in Grid', function () {
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

        expect($content)->toContain('Grid::make(2)')
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
    test('hidden:true fields get ->hidden() emitted', function () {
        $step = [
            'uuid' => 'x', 'slug' => 'stap', 'name' => 'S',
            'configuration' => ['components' => [
                [
                    'key' => 'organisatieInformatie',
                    'type' => 'fieldset',
                    'label' => 'Organisatie',
                    'hidden' => true,
                    'components' => [],
                ],
            ]],
        ];

        expect(generateStep($step))->toContain('->hidden()');
    });

    test('conditional.show=true with scalar eq emits ->visible() closure', function () {
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
            ->and($content)->toContain('->visible(fn');
    });

    test('conditional.show=false (verberg als) emits ->hidden() closure', function () {
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

        expect($content)->toContain('->hidden(fn')
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
                    'components' => [],
                ],
            ]],
        ];

        $content = generateStep($step);

        // Filament CheckboxList slaat selectboxes op als array van values,
        // niet als object — de closure gebruikt in_array.
        expect($content)->toContain("in_array('buiten'")
            ->and($content)->toContain("\$get('waarVindtHetEvenementPlaats')");
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
