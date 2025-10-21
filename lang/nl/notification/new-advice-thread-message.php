<?php

return [
    'label' => 'Nieuw reactie op adviesvraag',

    'mail' => [
        'subject' => 'Reactie van :sender over ":event" gemeente :municipality',
        'greeting' => 'Nieuw reactie op adviesvraag',
        'body' => 'Er is een reactie op een adviesvraag binnen gekomen van :sender voor :advisory voor het evenement :event.',
        'button' => 'Reactie bekijken',
    ],

    'database' => [
        'title' => 'Reactie van :sender over ":event" gemeente :municipality',
    ],
];
