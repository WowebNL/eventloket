<?php

return [
    'label' => 'Gebruiker',
    'plural_label' => 'Gebruikers',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'role' => [
            'label' => 'Rol',
        ],

    ],

    'actions' => [
        'invite' => [
            'label' => 'Gebruiker uitnodigen',
            'modal_submit_action_label' => 'Uitnodiging versturen',
            'form' => [
                'email' => [
                    'label' => 'E-mailadres',
                ],
                'make_admin' => [
                    'label' => 'Maak beheerder',
                    'helper_text' => 'Beheerders kunnen organisatiegegevens beheren en nieuwe gebruikers uitnodigen.',
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
