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

        'triggers_route_check' => [
            'label' => 'Route-check activeren',
            'helper_text' => 'Als aangevinkt, wordt na aanmaken van een zaak gecontroleerd of de route door andere gemeenten gaat en worden er automatisch deelzaken aangemaakt.',
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
