<?php

return [
    'label' => [
        'reviewer' => 'Nieuwe zaak',
        'organiser' => 'Nieuwe aanvraag',
    ],

    'mail' => [
        'subject' => [
            'reviewer' => 'Nieuw zaak ":event" beschikbaar',
            'organiser' => 'Nieuwe aanvraag voor ":event" ontvangen',
        ],
        'greeting' => [
            'reviewer' => 'Nieuw zaak beschikbaar',
            'organiser' => 'Nieuwe aanvraag ontvangen',
        ],
        'body' => [
            'reviewer' => 'Er is een nieuw zaak ontvangen voor ":event" bij :municipality.',
            'organiser' => 'Je nieuwe aanvraag voor ":event" bij :municipality is succesvol ontvangen.',
        ],
        'button' => [
            'reviewer' => 'Zaak bekijken',
            'organiser' => 'Aanvraag bekijken',
        ],
    ],

    'database' => [
        'title' => [
            'reviewer' => 'Nieuw zaak voor ":event"',
            'organiser' => 'Nieuwe aanvraag voor ":event" ontvangen',
        ],
        'body' => [
            'reviewer' => 'Er is een nieuw zaak ontvangen voor ":event" bij :municipality.',
            'organiser' => 'Je nieuwe aanvraag voor ":event" bij :municipality is succesvol ontvangen.',
        ],
    ],
];
