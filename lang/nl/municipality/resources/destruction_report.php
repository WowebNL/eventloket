<?php

return [
    'label' => 'Vernietigingsrapport',
    'plural_label' => 'Vernietigingsrapporten',

    'columns' => [
        'batch_number' => [
            'label' => 'Batchnummer',
        ],
        'destruction_date' => [
            'label' => 'Datum van vernietiging',
        ],
        'destruction_method' => [
            'label' => 'Wijze van vernietiging',
        ],
        'coordinator_name' => [
            'label' => 'Archiefcoördinator',
        ],
        'coordinator_function' => [
            'label' => 'Functie',
        ],
        'total_count' => [
            'label' => 'Totaal',
        ],
        'deleted_count' => [
            'label' => 'Vernietigd',
        ],
        'skipped_count' => [
            'label' => 'Overgeslagen',
        ],
        'failed_count' => [
            'label' => 'Mislukt',
        ],
        'items' => [
            'label' => 'Vernietigde zaken',
        ],
    ],

    'actions' => [
        'download_pdf' => [
            'label' => 'Download PDF',
        ],
    ],
];
