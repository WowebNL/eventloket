<?php

return [
    'title' => 'Verklaring van vernietiging',
    'intro' => 'Dit rapport is het bewijs van vernietiging van onderstaande zaken conform de Archiefwet. Dit rapport wordt permanent bewaard.',
    'eventloket_data' => [
        'title' => 'Verklaring van vernietiging Eventloket-gegevens',
        'intro' => 'Dit rapport legt vast welke gegevens Eventloket zelf over onderstaande zaken bewaarde en heeft vernietigd, '
            .'nadat het zaaksysteem meldde dat de zaak daar is vernietigd. De zaakgegevens zelf zijn in dat zaaksysteem vernietigd '
            .'en worden daar verantwoord. Dit rapport wordt permanent bewaard.',
        'items_heading' => 'Zaken waarvan de Eventloket-gegevens zijn vernietigd',
        'counts_value' => ':total zaken',
    ],
    'fields' => [
        'batch_number' => 'Batchnummer',
        'municipality' => 'Gemeente',
        'destruction_date' => 'Datum van vernietiging',
        'destruction_method' => 'Wijze van vernietiging',
        'coordinator' => 'Archiefcoördinator',
        'connection' => 'ZGW-koppeling',
        'destroyed_at' => 'Vernietigd op',
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
