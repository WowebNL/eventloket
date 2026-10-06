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
    */
    'destruction_method' => 'Permanente verwijdering van zaakgegevens uit OpenZaak en Eventloket (hard delete)',

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
