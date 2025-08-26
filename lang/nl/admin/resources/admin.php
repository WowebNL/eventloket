<?php

return [
    'label' => 'Beheerder',
    'plural_label' => 'Beheerders',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'role' => [
            'label' => 'Rol',
        ],

    ],

    'user' => [
        'label' => 'Adviseur',
        'plural_label' => 'Adviseurs',
    ],

    'actions' => [
        'invite' => [
            'label' => 'Gemeentelijk beheerder uitnodigen',
            'modal_submit_action_label' => 'Uitnodiging versturen',
            'form' => [
                'name' => [
                    'label' => 'Naam',
                ],
                'email' => [
                    'label' => 'E-mailadres',
                ],
                'role' => [
                    'label' => 'Rol',
                    'options' => [
                        'municipality_admin' => [
                            'label' => 'Gemeentelijk beheerder',
                            'description' => 'Gemeentelijk beheerders kunnen alleen de gegevens van hun gemeente beheren.',
                        ],
                        'admin' => [
                            'label' => 'Platformbeheerder',
                            'description' => 'Platformbeheerders kunnen alle organisatiegegevens beheren.',
                        ],
                    ],
                ],
                'municipalities' => [
                    'label' => 'Selecteer de gemeente(n) waar de gemeentelijk beheerder toegang tot heeft',
                ],
                'can_review' => [
                    'label' => 'Mag aanvragen behandelen',
                    'helper_text' => 'Vink dit aan als de gemeentelijk beheerder ook aanvragen moet kunnen behandelen',
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
