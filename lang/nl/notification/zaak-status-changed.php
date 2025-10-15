<?php

return [
    'label' => 'Statuswijziging aanvraag',

    'mail' => [
        'subject' => 'Status van je aanvraag voor ":event" is gewijzigd',
        'greeting' => 'Statuswijziging aanvraag',
        'body' => 'De status van je aanvraag voor het evenement :event bij :municipality is gewijzigd van ":old_status" naar ":new_status".',
        'button' => 'Aanvraag bekijken',
    ],

    'database' => [
        'title' => 'Statuswijziging voor ":event"',
        'body' => 'De status van je aanvraag bij :municipality is gewijzigd van ":old_status" naar ":new_status".',
    ],
];
