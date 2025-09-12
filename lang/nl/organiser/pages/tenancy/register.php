<?php

return [

    'label' => 'Organisatie registreren',

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

        'email' => [
            'label' => 'Algemeen e-mailadres',
        ],

        'phone' => [
            'label' => 'Algemeen telefoonnummer',
        ],
        'postcode' => [
            'label' => 'Postcode',
        ],
        'huisnummer' => [
            'label' => 'Huisnummer',
        ],
        'huisletter' => [
            'label' => 'Huisletter',
        ],
        'huisnummertoevoeging' => [
            'label' => 'Huisnummertoevoeging',
        ],
        'straatnaam' => [
            'label' => 'Straatnaam',
        ],
        'woonplaatsnaam' => [
            'label' => 'Woonplaatsnaam',
        ],
        'bagid' => [
            'label' => 'Identificatie in het Basisregistratie Adressen en Gebouwen (BAG)',
        ],

    ],

    'actions' => [

        'no_organisation' => [
            'label' => 'doorgaan zonder organisatie',
        ],

    ],

];
