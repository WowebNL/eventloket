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
        'regenerate' => [
            'label' => 'Vernietigingsrapport opnieuw genereren',
            'modal_description' => 'Het vernietigingsrapport en de bijbehorende PDF worden opnieuw opgebouwd uit de vastgelegde gegevens. Een bestaand rapport houdt zijn batchnummer en inhoud.',
            'notification' => [
                'title' => 'Vernietigingsrapport wordt opnieuw gegenereerd',
            ],
        ],
    ],
];
