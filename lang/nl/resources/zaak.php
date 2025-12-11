<?php

return [
    'label' => 'Zaak',
    'plural_label' => 'Zaken',

    'columns' => [
        'naam_evenement' => [
            'label' => 'Naam evenement',
        ],
        'public_id' => [
            'label' => 'Identificatie',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'registratiedatum' => [
            'label' => 'Registratiedatum',
        ],
        'zaaktype' => [
            'label' => 'Zaaktype',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
        'start_evenement' => [
            'label' => 'Start evenement',
        ],
        'eind_evenement' => [
            'label' => 'Eind evenement',
        ],
        'telefoon' => [
            'label' => 'Telefoonnummer organisatie',
        ],
        'telefoon-organiser' => [
            'label' => 'Telefoonnummer indiener',
        ],
        'email' => [
            'label' => 'E-mailadres organisatie',
        ],
        'email-organiser' => [
            'label' => 'E-mailadres indiener',
        ],
        'assigned_advisor_users' => [
            'label' => 'Toegewezen adviseurs',
        ],
        'aanwezigen' => [
            'label' => 'Aanwezigen',
        ],
        'types_evenement' => [
            'label' => 'Type(n) evenement',
        ],
        'handled_status_set_by_user' => [
            'label' => 'In behandeling door',
        ],
        'resultaat' => [
            'label' => 'Resultaat',
        ],
        'naam_locatie_evenement' => [
            'label' => 'Naam locatie evenement',
        ],
    ],
    'filters' => [
        'workingstock' => [
            'label' => 'Snelfilter werkvoorraad',
            'options' => [
                'me' => 'Mijn werkvoorraad',
                'new' => 'Nieuw',
                'all' => 'Alle zaken',
            ],
        ],
    ],
];
