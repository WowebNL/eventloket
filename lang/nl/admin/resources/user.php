<?php

return [
    'label' => 'Behandelaar',
    'plural_label' => 'Behandelaren',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

    ],

    'actions' => [
        'invite' => [
            'label' => 'Behandelaar uitnodigen',
            'modal_submit_action_label' => 'Uitnodiging versturen',
            'form' => [
                'name' => [
                    'label' => 'Naam',
                ],
                'email' => [
                    'label' => 'E-mailadres',
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
