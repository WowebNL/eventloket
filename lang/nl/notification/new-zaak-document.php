<?php

return [
    'label' => 'Nieuw of bijgewerkt document',

    'mail' => [
        'subject' => [
            'new' => 'Nieuw document voor ":event" toegevoegd',
            'updated' => 'Bijgewerkt document voor ":event" toegevoegd',
        ],
        'greeting' => 'Nieuw document beschikbaar',
        'body' => [
            'new' => 'Er is een nieuw document toegevoegd bij je aanvraag voor het evenement :event van :municipality.',
            'updated' => 'Er is een nieuwe versie toegevoegd van een eerder document bij je aanvraag voor het evenement :event van :municipality.',
        ],
        'button' => 'Document bekijken',
    ],

    'database' => [
        'title' => [
            'new' => 'Nieuw document voor ":event"',
            'updated' => 'Nieuwe versie van document voor ":event"',
        ],
        'body' => [
            'new' => 'Er is een nieuw document toegevoegd bij je aanvraag van :municipality.',
            'updated' => 'Er is een nieuwe versie toegevoegd van een document bij je aanvraag van :municipality.',
        ],
    ],
];
