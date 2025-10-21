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
            'new' => 'Er is een nieuw document toegevoegd met de titel :filename bij je aanvraag voor het evenement :event.',
            'updated' => 'Er is een nieuwe versie toegevoegd van een eerder document met de titel :filename bij je aanvraag voor het evenement :event.',
        ],
        'button' => 'Document bekijken',
    ],

    'database' => [
        'title' => [
            'new' => 'Nieuw document voor ":event"',
            'updated' => 'Nieuwe versie van document voor ":event"',
        ],
        'body' => [
            'new' => 'Er is een nieuw document toegevoegd bij je aanvraag met de titel :filename.',
            'updated' => 'Er is een nieuwe versie toegevoegd van een document met de titel :filename.',
        ],
    ],
];
