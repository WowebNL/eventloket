<?php

return [
    'label' => 'Zaaktype-koppeling',

    'mail' => [
        'subject' => [
            'unavailable' => 'Zaaktype ":zaaktype" is niet meer geldig',
            'restored' => 'Zaaktype ":zaaktype" is weer geldig',
            'blueprint_incomplete' => 'Zaaktype ":zaaktype" mist vereiste onderdelen',
            'main_unavailable' => 'Zaaktype ":zaaktype" (hoofdkoppeling) is niet meer geldig',
        ],
        'greeting' => [
            'unavailable' => 'Zaaktype niet meer geldig',
            'restored' => 'Zaaktype weer geldig',
            'blueprint_incomplete' => 'Zaaktype mist vereiste onderdelen',
            'main_unavailable' => 'Zaaktype in hoofdkoppeling niet meer geldig',
        ],
        'button' => 'Koppeling bekijken',
    ],

    'database' => [
        'title' => [
            'unavailable' => 'Zaaktype ":zaaktype" is niet meer geldig',
            'restored' => 'Zaaktype ":zaaktype" is weer geldig',
            'blueprint_incomplete' => 'Zaaktype ":zaaktype" mist vereiste onderdelen',
            'main_unavailable' => 'Zaaktype ":zaaktype" (hoofdkoppeling) is niet meer geldig',
        ],
    ],

    'body' => [
        'unavailable' => 'Het gekoppelde zaaktype ":zaaktype" van gemeente :municipality heeft geen geldige definitieve versie meer in de eigen ZGW-omgeving.',
        'restored' => 'Het gekoppelde zaaktype ":zaaktype" van gemeente :municipality heeft weer een geldige definitieve versie en wordt weer gebruikt voor nieuwe aanvragen.',
        'blueprint_incomplete' => 'De actuele versie van zaaktype ":zaaktype" mist onderdelen die Eventloket nodig heeft:',
        'main_unavailable' => 'Zaaktype ":zaaktype" in de hoofdcatalogus heeft geen geldige definitieve versie meer en is gedeactiveerd.',
        'fallback_active' => 'Nieuwe aanvragen gebruiken tijdelijk ":fallback" via de hoofdkoppeling.',
        'fallback_missing' => 'Er is geen terugval-zaaktype in de hoofdcatalogus gevonden; nieuwe aanvragen voor deze rol zullen mislukken totdat de koppeling hersteld is.',
    ],

    'slot' => [
        'initial_statustype' => 'Beginstatus',
        'eind_statustype' => 'Eindstatus',
        'initiator_roltype' => 'Initiator-roltype',
        'ingetrokken_resultaattype' => 'Resultaattype "Ingetrokken"',
        'aanvraag_informatieobjecttype' => 'Documenttype aanvraagformulier',
        'bijlage_informatieobjecttype' => 'Documenttype bijlagen',
        'eigenschap' => 'Eigenschap ":key"',
    ],

    'finding' => [
        'missing' => ':slot ontbreekt op het zaaktype.',
        'mapped_value_not_found' => ':slot: de gekoppelde waarde ":expected" bestaat niet meer.',
    ],
];
