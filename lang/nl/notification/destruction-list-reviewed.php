<?php

return [
    'label' => 'Vernietigingslijst beoordeeld',

    'mail' => [
        'subject' => [
            'approved' => 'Vernietigingslijst ":list" akkoord bevonden',
            'changes_requested' => 'Wijzigingen gevraagd voor vernietigingslijst ":list"',
        ],
        'greeting' => [
            'approved' => 'Vernietigingslijst akkoord',
            'changes_requested' => 'Wijzigingen gevraagd',
        ],
        'body' => [
            'approved' => 'De vernietigingslijst ":list" is akkoord bevonden door de beoordelaar. Je kunt de vernietiging nu bevestigen.',
            'changes_requested' => 'De beoordelaar heeft wijzigingen gevraagd voor de vernietigingslijst ":list".',
        ],
        'button' => 'Lijst bekijken',
    ],

    'database' => [
        'title' => [
            'approved' => 'Vernietigingslijst ":list" akkoord',
            'changes_requested' => 'Wijzigingen gevraagd voor ":list"',
        ],
        'body' => [
            'approved' => 'De vernietigingslijst ":list" is akkoord bevonden door de beoordelaar.',
            'changes_requested' => 'De beoordelaar heeft wijzigingen gevraagd voor de vernietigingslijst ":list".',
        ],
    ],
];
