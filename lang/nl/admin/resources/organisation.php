<?php

return [
    'label' => 'Organisatie',
    'plural_label' => 'Organisaties',

    'user' => [
        'label' => 'Organisator',
        'plural_label' => 'Organisatoren',

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
    ],

    'form' => [

        'name' => [
            'label' => 'Naam',
        ],

        'coc_number' => [
            'label' => 'KVK-nummer',
            'validation' => [
                'unique' => 'Er bestaat al een organisatie met dit :Attribute in ons systeem. Neem contact op met deze organisatie om toegang te krijgen.',
            ],
        ],

        'address' => [
            'label' => 'Adres',
        ],

        'bag_id' => [
            'label' => 'BAG Identificatie',
            'helper_text' => 'Basisregistratie Adressen en Gebouwen identificatie.',
        ],

        'email' => [
            'label' => 'Algemeen e-mailadres',
        ],

        'phone' => [
            'label' => 'Algemeen telefoonnummer',
        ],

    ],

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'coc_number' => [
            'label' => 'KVK-nummer',
        ],

        'address' => [
            'label' => 'Adres',
        ],

        'bag_id' => [
            'label' => 'BAG Identificatie',
        ],

        'email' => [
            'label' => 'Algemeen e-mailadres',
        ],

        'phone' => [
            'label' => 'Algemeen telefoonnummer',
        ],

    ],

    'widgets' => [

        'pending_invites' => [

            'action' => 'Openstaande uitnodigingen',

            'heading' => 'Openstaande organisatoruitnodigingen',

            'label' => 'Organisatoruitnodiging',
            'plural_label' => 'Organisatoruitnodigingen',

            'columns' => [

                'email' => [
                    'label' => 'E-mailadres',
                ],

                'name' => [
                    'label' => 'Naam',
                ],

                'role' => [
                    'label' => 'Rol',
                ],

                'created_at' => [
                    'label' => 'Aangemaakt op',
                ],

            ],

        ],

    ],
];
