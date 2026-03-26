<?php

return [
    'label' => 'Meldingvraag',
    'plural_label' => 'Meldingvragen',

    'columns' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
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
    'actions' => [
        'create' => [
            'label' => 'Variabele aanmaken',
        ],
    ],

    'form' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
            'helper_text' => 'De meldingvraag tekst zoals deze in de API en beheerschermen gebruikt wordt.',
        ],

        'is_active' => [
            'label' => 'Actief',
            'helper_text' => 'Als uitgeschakeld blijft de key beschikbaar in de API, maar met een lege waarde.',
        ],
    ],
];
