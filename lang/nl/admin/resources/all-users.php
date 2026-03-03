<?php

return [
    'label' => 'Gebruiker',
    'plural_label' => 'Alle gebruikers',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'role' => [
            'label' => 'Rol',
        ],

        'email_verified' => [
            'label' => 'E-mail geverifieerd',
        ],

        'created_at' => [
            'label' => 'Aangemaakt op',
        ],

    ],

    'infolist' => [
        'user_info' => 'Gebruikersinformatie',
        'email' => 'E-mail',
        'municipalities' => 'Gemeenten',
        'no_municipalities' => 'Geen gemeenten',
        'organisations' => 'Organisaties',
        'no_organisations' => 'Geen organisaties',
        'advisories' => 'Adviesdiensten',
        'no_advisories' => 'Geen adviesdiensten',
    ],

    'actions' => [
        'reset_2fa' => [
            'label' => '2FA resetten',
            'modal_heading' => 'Tweefactorauthenticatie resetten',
            'modal_description' => 'Weet u zeker dat u de tweefactorauthenticatie voor deze gebruiker wilt resetten? De gebruiker zal opnieuw 2FA moeten instellen bij de volgende login.',
            'modal_submit_action_label' => 'Resetten',
            'notification' => [
                'title' => '2FA gereset',
                'body' => 'Tweefactorauthenticatie is succesvol gereset voor :name.',
            ],
        ],
    ],
];
