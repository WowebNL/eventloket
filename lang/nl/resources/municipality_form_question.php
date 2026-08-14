<?php

return [
    'label' => 'Aanvullende vraag',
    'plural_label' => 'Aanvullende vragen',

    'table' => [
        'description' => 'Hier kunnen eigen vragen beheerd worden die de organisator in het formulier krijgt, in een aparte stap "Aanvullende vragen" vlak voor de bijlagen. Deze vragen zijn puur informatief: de antwoorden bepalen niet of er een melding of een vergunning nodig is. Er kunnen maximaal :max vragen ingesteld worden. Alleen actieve vragen worden getoond. De volgorde van de vragen kan worden aangepast door te klikken op "Volgorde aanpassen", vervolgens kunnen de vragen versleept worden naar de gewenste positie. Daarna kan op "Volgorde opslaan" worden geklikt om de nieuwe volgorde op te slaan. Let op: een wijziging aan deze vragen geldt direct voor alle openstaande concepten. Reeds ingediende aanvragen veranderen niet, want daarvan is de vragenlijst bij het indienen vastgelegd.',
        'empty_heading' => 'Nog geen aanvullende vragen',
        'empty_description' => 'Zolang er geen actieve vraag is, krijgt de organisator de stap "Aanvullende vragen" niet te zien.',
    ],

    'aanvraag_types' => [
        'vergunning' => 'Vergunningaanvraag',
        'melding' => 'Melding',
        'vooraankondiging' => 'Vooraankondiging',
    ],

    'columns' => [
        'order' => [
            'label' => 'Volgorde',
        ],
        'label' => [
            'label' => 'Vraag',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'show_for_aanvraag_types' => [
            'label' => 'Tonen bij',
            'all' => 'Alle aanvragen',
        ],
        'is_required' => [
            'label' => 'Verplicht',
        ],
        'is_active' => [
            'label' => 'Actief',
        ],
        'created_at' => [
            'label' => 'Aangemaakt op',
        ],
        'updated_at' => [
            'label' => 'Bijgewerkt op',
        ],
    ],

    'filters' => [
        'show_for_aanvraag_types' => [
            'label' => 'Tonen bij',
        ],
    ],

    'actions' => [
        'create' => [
            'label' => 'Vraag toevoegen',
        ],
        'disable_reordering' => [
            'label' => 'Volgorde opslaan',
        ],
        'enable_reordering' => [
            'label' => 'Volgorde aanpassen',
        ],
    ],

    'form' => [
        'order' => [
            'label' => 'Volgorde',
        ],
        'type' => [
            'label' => 'Type vraag',
            'helper_text' => 'Een tekstblok is een vrij invulveld. Bij één keuze kiest de organisator precies één optie, bij meerdere keuzes kan hij er meer aanvinken.',
        ],
        'label' => [
            'label' => 'Vraag',
            'helper_text' => 'De vraagtekst zoals die in het formulier getoond wordt.',
        ],
        'helper_text' => [
            'label' => 'Toelichting',
            'helper_text' => 'Optionele toelichting die onder de vraag getoond wordt.',
        ],
        'options' => [
            'label' => 'Antwoordopties',
            'helper_text' => 'Vul minimaal twee opties in. Typ een optie en druk op Enter om hem toe te voegen.',
        ],
        'show_for_aanvraag_types' => [
            'label' => 'Tonen bij',
            'helper_text' => 'Kies bij welke soorten aanvragen deze vraag gesteld wordt. Niets aanvinken betekent: bij alle aanvragen.',
        ],
        'is_required' => [
            'label' => 'Verplicht',
            'helper_text' => 'Een verplichte vraag moet beantwoord zijn voordat de organisator verder kan.',
        ],
        'is_active' => [
            'label' => 'Actief',
            'helper_text' => 'Alleen actieve vragen worden in het formulier getoond. Staan er geen actieve vragen, dan verschijnt de stap "Aanvullende vragen" helemaal niet.',
        ],
    ],
];
