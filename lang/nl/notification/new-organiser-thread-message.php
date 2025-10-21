<?php

return [
    'label' => 'Reacties op organisatievragen',
    'organiser_label' => 'Reacties op vragen aan/van behandelaar',

    'mail' => [
        'subject' => 'Reactie van :sender (:organisation) over ":event"',
        'greeting' => 'Nieuw reactie op organisatievraag',
        'body' => 'Er is een reactie op een organisatievraag binnen gekomen van :sender (:organisation) voor het evenement :event.',
        'button' => 'Reactie op organisatievraag bekijken',
    ],

    'organiser_mail' => [
        'subject' => 'De behandelaar voor het evenement ":event" heeft een reactie gegeven op een vraag.',
        'greeting' => 'Nieuwe reactie van behandelaar',
        'body' => 'De behandelaar van uw aanvraag voor het evenement :event heeft een reactie gegeven op een vraag.',
        'button' => 'Bekijk reactie',
    ],

    'database' => [
        'title' => 'Reactie op organisatievraag van :sender (:organisation) over ":event".',
    ],

    'organiser_database' => [
        'title' => 'De behandelaar van ":event" heeft een reactie geplaatst bij een vraag.',
    ],
];
