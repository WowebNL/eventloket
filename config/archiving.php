<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Destruction method
    |--------------------------------------------------------------------------
    |
    | The default description of how zaken are destroyed, included in the
    | destruction report. The archive coordinator can adjust it per list
    | when confirming the destruction.
    |
    | This report covers the zaakdata in OpenZaak only. What Eventloket itself
    | held about the zaak is destroyed afterwards and accounted for in the
    | separate Eventloket data report below.
    |
    */
    'destruction_method' => 'Permanente verwijdering van zaakgegevens uit OpenZaak (hard delete)',

    /*
    |--------------------------------------------------------------------------
    | Eventloket data destruction method
    |--------------------------------------------------------------------------
    |
    | How the data Eventloket itself held about a zaak was destroyed, included
    | in the automatically generated Eventloket-data report. The zaakdata is
    | destroyed in the zaaksysteem and accounted for by the other report.
    |
    */
    'eventloket_destruction_method' => 'Permanente verwijdering van de Eventloket-gegevens (threads, berichten, notificaties, logboek en, waar aanwezig, het formulierinzending-object; zie per zaak), nadat het zaaksysteem bevestigde dat de zaak is vernietigd',

    /*
    |--------------------------------------------------------------------------
    | Organiser account anonymisation
    |--------------------------------------------------------------------------
    |
    | Organiser accounts without any remaining zaken that have been inactive
    | for at least this number of months are anonymised by the
    | archiving:anonymise-inactive-organisers command.
    |
    */
    'organiser_inactivity_months' => env('ARCHIVING_ORGANISER_INACTIVITY_MONTHS', 24),

    /*
    |--------------------------------------------------------------------------
    | Destruction report storage
    |--------------------------------------------------------------------------
    |
    | The filesystem disk the destruction report PDFs are written to. The
    | report is a permanent legal record, so point this at durable storage
    | (for example an S3 bucket) in any environment with ephemeral disks.
    |
    */
    'report_disk' => env('ARCHIVING_REPORT_DISK', 'local'),

];
