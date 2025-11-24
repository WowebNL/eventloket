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
            'notification' => 'Rol geupdate',
        ],

    ],

    'form' => [

        'name' => [
            'label' => 'Naam',
        ],

        'email' => [
            'label' => 'E-mailadres',
        ],

        'phone' => [
            'label' => 'Telefoonnummer',
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
                'name' => [
                    'label' => 'Naam',
                ],
                'email' => [
                    'label' => 'E-mailadres',
                    'validation' => [
                        'already_invited' => 'Dit :attribute is al uitgenodigd voor deze organisatie.',
                    ],
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

    'widgets' => [

        'pending_invites' => [

            'action' => 'Openstaande uitnodigingen',

            'heading' => 'Openstaande adviseur uitnodigingen',

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
