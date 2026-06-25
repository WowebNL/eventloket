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
                    'validation' => [
                        'already_invited' => 'Dit :attribute is al uitgenodigd.',
                    ],
                ],
                'is_coordinator' => [
                    'label' => 'Uitnodigen als coördinator',
                    'helper_text' => 'Vink dit aan als de behandelaar zaken moet kunnen verdelen onder behandelaars. Een coördinator ontvangt meldingen van nieuwe zaken en wijst behandelaars toe, maar behandelt zaken niet zelf.',
                ],
            ],
            'notification' => [
                'title' => 'Uitnodiging verstuurd',
            ],
        ],
    ],
];
