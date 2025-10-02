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
    ],
];
