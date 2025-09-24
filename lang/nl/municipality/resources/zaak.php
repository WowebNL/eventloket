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
        'organisator' => [
            'label' => 'Organisator',
        ],
        'organisation' => [
            'label' => 'Organisatie',
        ],
        'uiterlijkeEinddatumAfdoening' => [
            'label' => 'Uiterlijke einddatum afdoening',
        ],
    ],

    'infolist' => [
        'sections' => [
            'information' => [
                'label' => 'Informatie',
                'description' => 'Informatie over de zaak',
            ],
            'actions' => [
                'label' => 'Acties',
                'description' => 'Voer wijzigingen uit binnen de zaak',
                'actions' => [
                    'edit_risico_classificatie' => [
                        'label' => 'Wijzigen',
                    ],
                ],
            ],
        ],
        'tabs' => [
            'documents' => [
                'label' => 'Bestanden',
            ],
            'messages' => [
                'label' => 'Organisatievragen',
            ],
            'advice_requests' => [
                'label' => 'Adviesvragen',
            ],
            'locations' => [
                'label' => 'Locaties',
            ],
            'log' => [
                'label' => 'Log',
            ],
        ],
    ],
];
