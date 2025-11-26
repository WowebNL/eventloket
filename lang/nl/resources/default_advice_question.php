<?php

return [
    'label' => 'Standaard adviesvraag',
    'plural_label' => 'Standaard adviesvragen',

    'form' => [
        'advisory_id' => [
            'label' => 'Adviesdienst',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
        'title' => [
            'label' => 'Titel',
        ],
        'description' => [
            'label' => 'Omschrijving',
        ],
        'response_deadline_days' => [
            'label' => 'Reactietermijn (dagen)',
            'helper' => 'Aantal dagen waarbinnen de adviesdienst moet reageren',
        ],
    ],

    'columns' => [
        'advisory' => [
            'label' => 'Adviesdienst',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
        'title' => [
            'label' => 'Titel',
        ],
        'response_deadline_days' => [
            'label' => 'Reactietermijn',
        ],
        'created_at' => [
            'label' => 'Aangemaakt op',
        ],
    ],

    'filters' => [
        'advisory' => [
            'label' => 'Adviesdienst',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
    ],
];
