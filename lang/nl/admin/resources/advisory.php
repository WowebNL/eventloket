<?php

return [
    'label' => 'Adviesdienst',
    'plural_label' => 'Adviesdiensten',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'can_view_any_zaak' => [
            'label' => 'Alle zaken inzien',
            'helper_text' => 'Maakt het mogelijk om alle zaken en documenten te bekijken. Bewerken is alleen toegestaan bij zaken waarvoor advies is gevraagd.',
        ],

        'municipalities' => [
            'label' => 'Gemeenten',
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
                    'validation' => [
                        'already_invited' => 'Dit :attribute is al uitgenodigd voor deze adviesdienst.',
                    ],
                ],
                'make_admin' => [
                    'label' => 'Maak beheerder',
                    'helper_text' => 'Beheerders kunnen adviesdienstgegevens beheren en nieuwe gebruikers uitnodigen.',
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
