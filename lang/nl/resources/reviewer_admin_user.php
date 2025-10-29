<?php

return [
    'label' => 'Behandelaar met beheerdersrechten',
    'plural_label' => 'Behandelaren met beheerdersrechten',

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
                    'validation' => [
                        'already_invited' => 'Dit :attribute is al uitgenodigd.',
                    ],
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
