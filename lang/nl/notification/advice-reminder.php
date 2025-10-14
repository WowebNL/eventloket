<?php

return [
    'label' => 'Herinnering adviesdeadline',

    'mail' => [
        'subject' => 'Herinnering: adviesdeadline voor ":event" :when',
        'greeting' => 'Herinnering adviesvraag',
        'body' => 'Er is nog geen advies ingediend voor de adviesvraag van :municipality voor :advisory over :event. De deadline is :when.',
        'button' => 'Adviesvraag bekijken',
    ],

    'database' => [
        'title' => 'Adviesdeadline voor ":event" :when',
        'body' => 'Nog geen advies ingediend voor de adviesvraag van :municipality voor :advisory. De deadline is :when.',
    ],

    // Reusable relative-time snippets
    'when' => '{0} vandaag|{1} over 1 dag|[2,*] over :count dagen',
];
