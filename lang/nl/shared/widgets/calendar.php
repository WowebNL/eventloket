<?php

return [
    'title' => 'Kalender',
    'modal_title' => 'Evenement',
    'view_case' => 'Bekijk zaak',
    'navigation_label' => 'Kalender weergave',
    'navigation_label_list' => 'Lijst weergave',
    'navigation_group_label' => 'Evenementen kalender',

    'filters' => [
        'range' => [
            'from' => [
                'label' => 'Vanaf',
                'placeholder' => 'Altijd',
            ],
            'to' => [
                'label' => 'Tot en met',
                'placeholder' => 'Onbeperkt',
            ],
        ],
    ],

    'actions' => [
        'import' => [
            'label' => 'Evenementen importeren',
            'completed_notification' => [
                'body' => 'Je evenementen import is voltooid en :count rijen zijn geïmporteerd',
                'failed' => ' :count rijen konden niet geïmporteerd worden.',
            ],
        ],
    ],
];
