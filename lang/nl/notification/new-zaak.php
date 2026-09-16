<?php

return [
    // Used in place of the event name when the zaak carries none (a doorkomst
    // deelzaak whose zaaktype does not know the eigenschap, a recovered zaak).
    'unnamed_event' => 'onbekend evenement',

    'label' => [
        'reviewer' => 'Nieuwe zaak',
        'organiser' => 'Nieuwe aanvraag',
    ],

    'mail' => [
        'subject' => [
            'reviewer' => 'Nieuwe zaak ":event" beschikbaar',
            'organiser' => 'Nieuwe aanvraag voor ":event" ontvangen',
        ],
        'greeting' => [
            'reviewer' => 'Nieuwe zaak beschikbaar',
            'organiser' => 'Nieuwe aanvraag ontvangen',
        ],
        'body' => [
            'reviewer' => 'Er is een nieuwe zaak ontvangen voor ":event" bij :municipality.',
            'organiser' => 'Je nieuwe aanvraag voor ":event" bij :municipality is succesvol ontvangen.',
        ],
        'button' => [
            'reviewer' => 'Zaak bekijken',
            'organiser' => 'Aanvraag bekijken',
        ],
    ],

    'database' => [
        'title' => [
            'reviewer' => 'Nieuwe zaak voor ":event"',
            'organiser' => 'Nieuwe aanvraag voor ":event" ontvangen',
        ],
        'body' => [
            'reviewer' => 'Er is een nieuwe zaak ontvangen voor ":event" bij :municipality.',
            'organiser' => 'Je nieuwe aanvraag voor ":event" bij :municipality is succesvol ontvangen.',
        ],
    ],
];
