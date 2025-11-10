<?php

return [
    'label' => 'Variabele',
    'plural_label' => 'Variabelen',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'key' => [
            'label' => 'Sleutel',
        ],

        'type' => [
            'label' => 'Type',
        ],

        'value' => [
            'label' => 'Waarde',
        ],

        'is_default' => [
            'label' => 'Is standaard',
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

        'name' => [
            'label' => 'Naam',
        ],

        'key' => [
            'label' => 'Sleutel',
            'info' => 'De sleutel mag alleen letters, cijfers en underscores (_) bevatten.',
        ],

        'type' => [
            'label' => 'Type',
        ],

        'value' => [
            'label' => 'Waarde',
        ],

        'start' => [
            'label' => 'Start',
        ],

        'end' => [
            'label' => 'Einde',
        ],

        'order' => [
            'label' => 'Volgorde van de vraag',
        ],
    ],
];
