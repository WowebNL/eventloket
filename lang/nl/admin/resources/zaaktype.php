<?php

return [
    'label' => 'Zaaktype',
    'plural_label' => 'Zaaktypen',

    'form' => [
        'name' => [
            'label' => 'Naam',
        ],

        'role' => [
            'label' => 'Type (Eventloket-rol)',
            'helper_text' => 'Bepaalt op welke rol dit zaaktype wordt gefilterd in de kalender en zaken-overzichten.',
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
            'managed_by_municipality' => 'Deze gemeente beheert de route-check in haar eigen zaaktype-koppeling.',
        ],

        'hidden_resultaat_types' => [
            'label' => 'Te verbergen resultaten',
            'helper_text' => 'Selecteer welke resultaten (bijv. "Ingetrokken") niet zichtbaar moeten zijn in de kalender en lijstweergave',
            'managed_by_municipality' => 'Deze gemeente beheert de te verbergen resultaten in haar eigen zaaktype-koppeling.',
        ],
    ],

    'columns' => [
        'role' => [
            'label' => 'Type',
        ],

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

        'connection' => [
            'label' => 'Bron-instantie',
            'main' => 'Hoofdcatalogus',
            'mismatch_tooltip' => 'Deze gemeente gebruikt een eigen ZGW-instantie. Dit zaaktype uit de hoofdcatalogus wordt waarschijnlijk niet gebruikt.',
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
