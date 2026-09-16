<?php

return [
    'download_documents' => [
        // Written into the archive when a selected document could not be put in
        // it, so a download never looks complete while it is not. Says nothing
        // technical: the reader can do nothing with a status code, only with the
        // fact that something is missing and what to do about it. The two causes
        // are listed apart because the answer differs: a file that could not be
        // fetched is still there and trying again can help, a file that is no
        // longer attached to the application will not come back.
        'missing' => [
            'file_name' => 'ONTBREKENDE-BESTANDEN.txt',
            'intro' => 'Niet alle geselecteerde bestanden zitten in deze download.',
            'unnamed' => 'Een bestand waarvan de naam niet meer te achterhalen is',
            'unretrievable' => [
                'heading' => 'Niet opgehaald:',
                'outro' => 'Deze bestanden staan nog bij de aanvraag. Probeer het later opnieuw, of open ze daar in :app_name.',
            ],
            'gone' => [
                'heading' => 'Niet meer bij de aanvraag:',
                'outro' => 'Deze bestanden zijn niet meer aan de aanvraag gekoppeld. Opnieuw downloaden levert ze niet alsnog op.',
            ],
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
