<?php

return [
    'label' => 'Archiefgebruiker',
    'plural_label' => 'Archiefgebruikers',

    'columns' => [
        'name' => [
            'label' => 'Naam',
        ],
        'role' => [
            'label' => 'Rol',
            'notification' => 'Rol gewijzigd',
        ],
    ],

    'actions' => [
        'invite' => [
            'form' => [
                'name' => [
                    'label' => 'Naam',
                ],
                'email' => [
                    'label' => 'E-mailadres',
                    'validation' => [
                        'already_invited' => 'Dit e-mailadres is al uitgenodigd.',
                    ],
                ],
                'role' => [
                    'label' => 'Rol',
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
