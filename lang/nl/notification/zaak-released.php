<?php

return [
    'label' => 'Zaak vrijgegeven',

    'mail' => [
        'subject' => 'Zaak ":event" is vrijgegeven',
        'greeting' => 'Zaak vrijgegeven',
        'body' => ':releasedBy heeft de behandeling van de zaak voor ":event" bij :municipality vrijgegeven. De zaak heeft momenteel geen behandelaar.',
        'button' => 'Zaak bekijken',
    ],

    'database' => [
        'title' => ':releasedBy heeft zaak ":event" vrijgegeven',
    ],
];
