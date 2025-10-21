
<?php

return [
    'label' => 'Nieuwe organisatievraag',
    'organiser_label' => 'Nieuwe vraag van behandelaar',

    'mail' => [
        'subject' => 'Organisatievraag voor het evenement ":event" van organisatie :organisation',
        'greeting' => 'Nieuwe organisatievraag',
        'body' => 'Er is een organisatievraag binnen gekomen voor het evenement :event van organisatie :organisation.',
        'button' => 'Organisatievraag bekijken',
    ],

    'organiser_mail' => [
        'subject' => 'De behandelaar voor het evenement ":event" heeft een vraag voor u klaargezet',
        'greeting' => 'Nieuwe vraag van behandelaar',
        'body' => 'Er is een vraag van de behandelaar van uw aanvraag binnen gekomen voor het evenement :event.',
        'button' => 'Bekijk vraag',
    ],

    'organiser_database' => [
        'title' => 'Nieuwe vraag van de behandelaar voor ":event"',
    ],

    'database' => [
        'title' => 'Organisatievraag ":event" van organisatie :organisation',
    ],
];
