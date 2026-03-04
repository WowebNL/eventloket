<?php

return [
    'label' => 'Zaaktype',
    'plural_label' => 'Zaaktypen',

    'form' => [
        'name' => [
            'label' => 'Naam',
        ],

        'zgw_zaaktype_url' => [
            'label' => 'ZGW Zaaktype URL',
        ],

        'municipality_id' => [
            'label' => 'Gemeente',
        ],

        'is_active' => [
            'label' => 'Actief',
        ],

        'hidden_resultaat_types' => [
            'label' => 'Te verbergen resultaten',
            'helper_text' => 'Selecteer welke resultaten (bijv. "Ingetrokken") niet zichtbaar moeten zijn in de kalender en lijstweergave',
        ],
    ],

    'columns' => [
        'id' => [
            'label' => 'ID',
        ],

        'name' => [
            'label' => 'Naam',
        ],

        'zgw_zaaktype_url' => [
            'label' => 'ZGW Zaaktype URL',
        ],

        'municipality' => [
            'label' => 'Gemeente',
        ],

        'is_active' => [
            'label' => 'Actief',
        ],

        'created_at' => [
            'label' => 'Aangemaakt op',
        ],

        'updated_at' => [
            'label' => 'Gewijzigd op',
        ],
    ],
];
