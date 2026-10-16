<?php

return [
    'title' => 'Verklaring van vernietiging',
    'intro' => 'Dit rapport is het bewijs van vernietiging van onderstaande zaken conform de Archiefwet. Dit rapport wordt permanent bewaard.',
    'fields' => [
        'batch_number' => 'Batchnummer',
        'municipality' => 'Gemeente',
        'destruction_date' => 'Datum van vernietiging',
        'destruction_method' => 'Wijze van vernietiging',
        'coordinator' => 'Archiefcoördinator',
        'counts' => 'Aantallen',
        'counts_value' => ':total zaken totaal, :deleted vernietigd, :skipped overgeslagen, :failed mislukt',
    ],
    'items_heading' => 'Vernietigde zaken',
    'columns' => [
        'zaaknummer' => 'Zaaknummer',
        'zaaktype' => 'Zaaktype',
        'naam_evenement' => 'Evenement',
        'grondslag' => 'Grondslag (selectielijst)',
        'bewaartermijn' => 'Bewaartermijn',
        'archiefactiedatum' => 'Archiefactiedatum',
        'status' => 'Status',
    ],
];
