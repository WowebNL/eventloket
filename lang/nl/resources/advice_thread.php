<?php

return [
    'label' => 'Adviesvraag',
    'plural_label' => 'Adviesvragen',

    'form' => [

        'advisory_id' => [
            'label' => 'Adviesdienst',
        ],

        'advice_due_at' => [
            'label' => 'Deadline',
        ],

        'title' => [
            'label' => 'Titel',
        ],

        'body' => [
            'label' => 'Omschrijving',
        ],
    ],

    'columns' => [

        'title' => [
            'label' => 'Titel',
        ],

        'event' => [
            'label' => 'Evenement',
        ],

        'organisation' => [
            'label' => 'Organisatie',
        ],

        'municipality' => [
            'label' => 'Gemeente',
        ],

        'name' => [
            'label' => 'Naam',
        ],

        'advice_status' => [
            'label' => 'Advies status',
        ],

        'advisory' => [
            'label' => 'Adviesdienst',
        ],

        'advice_due_at' => [
            'label' => 'Deadline',
        ],

        'created_by' => [
            'label' => 'Aangemaakt door',
        ],

        'assigned_users' => [
            'label' => 'Toegewezen adviseurs',
        ],

        'unread_messages_count' => [
            'label' => 'Ongelezen berichten',
        ],

    ],

    'actions' => [
        'assign' => [
            'label' => 'Adviseur toewijzen',
            'form' => [
                'advisors' => [
                    'label' => 'Adviseurs',
                ],
            ],
        ],
        'assign_to_self' => [
            'label' => 'Jezelf toewijzen',
        ],
    ],

    'widgets' => [
        'inbox' => [
            'heading' => 'Recente adviesvragen',
        ],
    ],
];
