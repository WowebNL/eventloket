<?php

return [
    'label' => 'applicatie',
    'plural_label' => 'applicaties',

    'columns' => [
        'id' => [
            'label' => 'Client ID',
            'copy_label' => 'Client ID gekopieerd',
        ],
        'name' => [
            'label' => 'Naam',
        ],
        'secret' => [
            'label' => 'Geheime sleutel',
            'helper_text' => 'Let op: de geheime sleutel wordt nooit meer getoond in de applicatie, sla deze dus goed op. Een geheime sleutel moet aan dezelfde voorwaarden als een wachtwoord voldoen.',
        ],
        'active_tokens_count' => [
            'label' => 'Aantal actieve tokens',
        ],
    ],
];
