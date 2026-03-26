<?php

return [
    'label' => 'Meldingvraag',
    'plural_label' => 'Meldingvragen',

    'columns' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
        ],

        'is_active' => [
            'label' => 'Actief',
        ],

        'placeholder_value' => [
            'label' => 'Placeholder waarde',
        ],

        'created_at' => [
            'label' => 'Aangemaakt op',
        ],
        'updated_at' => [
            'label' => 'Bijgewerkt op',
        ],

    ],
    'actions' => [
        'create' => [
            'label' => 'Variabele aanmaken',
        ],
    ],

    'form' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
            'helper_text' => 'De meldingvraag tekst. Gebruikt XX als placeholder voor dynamische waardes.',
        ],

        'is_active' => [
            'label' => 'Actief',
            'helper_text' => 'Als uitgeschakeld wordt deze vraag niet getoond in de API',
        ],

        'placeholder_value' => [
            'label' => 'Placeholder waarde',
            'helper_text' => 'Waarde voor XX placeholder in de vraag (optioneel)',
        ],
    ],
];
