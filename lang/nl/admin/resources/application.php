<?php

return [
    'label' => 'applicatie',
    'plural_label' => 'applicaties',

    'columns' => [
        'name' => [
            'label' => 'Naam',
        ],
        'all_endpoints' => [
            'label' => 'Applicatie heeft toegang tot alle api endpoints',
        ],
        'created_at' => [
            'label' => 'Gemaakt op',
        ],
        'updated_at' => [
            'label' => 'Laatst bijgewerkt op',
        ],
    ],

    'resource_information' => 'Binnen "Applicaties" kunnen externe applicaties worden geregistreerd en beheerd. Aan een applicatie kun je een "client" koppelen. Een client kan vervolgens worden gebruikt om via oAuth2 een toegangstoken tot de api te verkrijgen.',

];
