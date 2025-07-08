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

    ],

];
