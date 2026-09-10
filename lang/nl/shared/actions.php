<?php

return [
    'download_documents' => [
        // Written into the archive when a selected document could not be put in
        // it, so a download never looks complete while it is not. Says nothing
        // technical: the reader can do nothing with a status code, only with the
        // fact that something is missing and where to find it instead.
        'missing' => [
            'file_name' => 'ONTBREKENDE-BESTANDEN.txt',
            'intro' => 'De volgende bestanden konden niet worden opgehaald en zitten niet in deze download:',
            'outro' => 'Deze bestanden blijven beschikbaar bij de aanvraag in :app_name. Probeer het later opnieuw.',
        ],
    ],

    'invite' => [
        'label' => ':Model uitnodigen',
        'modal_submit_action_label' => 'Uitnodiging versturen',
    ],

    'pending_invites' => [
        'label' => 'Openstaande uitnodigingen',
    ],
];
