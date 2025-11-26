<?php

return [
    'label' => 'Vraag',
    'plural_label' => 'Vragen',

    'columns' => [

        'type' => [
            'label' => 'Type',
        ],

        'unread_messages_count' => [
            'label' => 'Ongelezen berichten',
        ],

        'latest_message' => [
            'label' => 'Laatste bericht',
        ],

    ],

    'widgets' => [
        'inbox' => [
            'heading' => 'Recente vragen',
        ],
    ],

    'filters' => [
        'unread_messages' => [
            'label' => 'Ongelezen',
            'options' => [
                'unread' => 'Ongelezen',
                'all' => 'Alles',
            ],
        ],
        'assigned' => [
            'label' => 'Toegewezen',
            'options' => [
                'unassigned' => 'Niet toegewezen',
                'self' => 'Aan mij',
                'all' => 'Alles',
            ],
        ],
    ],

    'actions' => [
        'request_advice' => [
            'label' => 'Advies uitvragen',
            'success' => 'Adviesvraag is uitgestuurd',
            'already_requested' => 'Deze adviesvraag is al uitgestuurd',
        ],
    ],
];
