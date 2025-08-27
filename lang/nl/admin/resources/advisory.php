<?php

return [
    'label' => 'Adviesdienst',
    'plural_label' => 'Adviesdiensten',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

    ],

    'user' => [
        'label' => 'Adviseur',
        'plural_label' => 'Adviseurs',
    ],

    'actions' => [
        'invite' => [
            'label' => 'Adviseur uitnodigen',
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

    'widgets' => [

        'pending_invites' => [

            'action' => 'Openstaande uitnodigingen',

            'heading' => 'Openstaande adviseuruitnodigingen',

            'label' => 'Adviseuruitnodiging',
            'plural_label' => 'Adviseuruitnodigingen',

            'columns' => [

                'email' => [
                    'label' => 'E-mailadres',
                ],

                'name' => [
                    'label' => 'Naam',
                ],

                'created_at' => [
                    'label' => 'Aangemaakt op',
                ],

            ],

        ],

    ],
];
