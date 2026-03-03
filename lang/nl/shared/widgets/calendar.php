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
                'body' => '{1} Je evenementen import is voltooid en :count rij is geïmporteerd|[2,*] Je evenementen import is voltooid en :count rijen zijn geïmporteerd',
                'failed' => '{1} :count rij kon niet geïmporteerd worden.|[2,*] :count rijen konden niet geïmporteerd worden.',
            ],
        ],
        'export' => [
            'label' => 'Evenementen exporteren',
            'completed_notification' => [
                'body' => '{1} Je evenementen export is afgerond en :count rij is geëxporteerd|[2,*] Je evenementen export is afgerond en :count rijen zijn geëxporteerd',
                'failed' => '{1} :count rij kon niet worden geëxporteerd.|[2,*] :count rijen konden niet worden geëxporteerd.',
            ],
        ],
    ],
];
