<?php

return [
    'label' => 'Statuskleur',
    'plural_label' => 'Statuskleuren',

    'form' => [
        'status_name' => [
            'label' => 'Status',
            'helper_text' => 'De naam van de status zoals deze voorkomt op de zaak (bijv. "Ontvangen", "In behandeling", "Afgerond").',
        ],

        'resultaat' => [
            'label' => 'Resultaat',
            'helper_text' => 'Alleen van toepassing bij een eindstatus (bijv. "Verleend", "Geweigerd", "Ingetrokken"). Laat leeg als deze kleur voor alle resultaten van deze status moet gelden.',
            'unique' => 'Voor deze combinatie van status en resultaat is al een kleur ingesteld.',
        ],

        'color' => [
            'label' => 'Kleur',
        ],
    ],

    'columns' => [
        'status_name' => [
            'label' => 'Status',
        ],

        'resultaat' => [
            'label' => 'Resultaat',
        ],

        'color' => [
            'label' => 'Kleur',
        ],
    ],
];
