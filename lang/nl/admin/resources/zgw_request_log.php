<?php

return [

    'label' => 'ZGW-logregel',
    'plural_label' => 'ZGW-logboek',

    'columns' => [
        'created_at' => ['label' => 'Tijdstip'],
        'method' => ['label' => 'Methode'],
        'resource' => ['label' => 'Resource'],
        'status_code' => ['label' => 'Status'],
        'user' => ['label' => 'Gebruiker'],
        'municipality' => ['label' => 'Gemeente', 'placeholder' => 'Algemeen'],
        'connection' => ['label' => 'Connectie'],
    ],

    'filters' => [
        'failed' => ['label' => 'Alleen mislukte aanvragen'],
        'method' => ['label' => 'Methode'],
        'connection' => ['label' => 'Connectie'],
        'municipality' => ['label' => 'Gemeente'],
    ],

    'connections' => [
        'main' => 'Hoofdverbinding (algemeen)',
    ],

];
