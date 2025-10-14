<?php

return [
    'label' => 'Nieuwe organisatievraag',

    'mail' => [
        'subject' => 'Organisatievraag ":event" binnen gemeente :municipality',
        'greeting' => 'Nieuwe organisatievraag',
        'body' => 'Er is een organisatievraag binnen gekomen bij :municipality voor het evenement :event van organisatie :organisation.',
        'button' => 'Organisatievraag bekijken',
    ],

    'database' => [
        'title' => 'Organisatievraag ":event" van gemeente :municipality',
    ],
];
