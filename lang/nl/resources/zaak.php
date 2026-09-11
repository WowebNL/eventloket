<?php

return [
    'label' => 'Zaak',
    'plural_label' => 'Zaken',

    'columns' => [
        'naam_evenement' => [
            'label' => 'Naam evenement',
        ],
        'public_id' => [
            'label' => 'Identificatie',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'status_color' => [
            'label' => 'Kleur',
        ],
        'registratiedatum' => [
            'label' => 'Registratiedatum',
        ],
        'zaaktype' => [
            'label' => 'Zaaktype',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
        'intern_zaaknummer' => [
            'label' => 'Intern zaaknummer',
        ],
        'start_evenement' => [
            'label' => 'Start evenement',
        ],
        'eind_evenement' => [
            'label' => 'Eind evenement',
        ],
        'dagen_evenement' => [
            'label' => 'Evenement per dag',
        ],
        'dagen_opbouw' => [
            'label' => 'Opbouw per dag',
        ],
        'dagen_afbouw' => [
            'label' => 'Afbouw per dag',
        ],
        'telefoon' => [
            'label' => 'Telefoonnummer organisatie',
        ],
        'naam-organiser' => [
            'label' => 'Naam indiener',
        ],
        'telefoon-organiser' => [
            'label' => 'Telefoonnummer indiener',
        ],
        'email' => [
            'label' => 'E-mailadres organisatie',
        ],
        'email-organiser' => [
            'label' => 'E-mailadres indiener',
        ],
        'assigned_advisor_users' => [
            'label' => 'Toegewezen adviseurs',
        ],
        'aanwezigen' => [
            'label' => 'Aanwezigen',
        ],
        'advisors' => [
            'label' => 'Adviseurs',
        ],
        'types_evenement' => [
            'label' => 'Type(n) evenement',
        ],
        'handled_status_set_by_user' => [
            'label' => 'In behandeling door',
        ],
        'reviewer_user' => [
            'label' => 'Behandelaar',
        ],
        'resultaat' => [
            'label' => 'Resultaat',
        ],
        'municipality' => [
            'label' => 'Gemeente',
        ],
        'naam_locatie_evenement' => [
            'label' => 'Naam locatie evenement',
        ],
        'locaties_evenement' => [
            'label' => 'Locaties evenement',
        ],
        'start_opbouw' => [
            'label' => 'Start opbouw',
        ],
        'eind_opbouw' => [
            'label' => 'Eind opbouw',
        ],
        'start_afbouw' => [
            'label' => 'Start afbouw',
        ],
        'eind_afbouw' => [
            'label' => 'Eind afbouw',
        ],
        'vervangt_vooraankondiging' => [
            'label' => 'Vervangt vooraankondiging',
        ],
        'opgevolgd_door' => [
            'label' => 'Opgevolgd door',
        ],
    ],
    'documents' => [
        // Shown when the documents API hands over the list of a zaak but
        // refuses one or more of the documents themselves. Deliberately says
        // nothing technical: the reader can do nothing with a status code, only
        // with the fact that something is missing and that it is not their doing.
        'unreadable' => [
            'title' => '{1} Eén bestand kan nu niet worden getoond|[2,*] :count bestanden kunnen nu niet worden getoond',
            'description' => 'Dit ligt niet aan uw aanvraag. Probeer het later opnieuw, of neem contact op met de beheerder als u een bestand nu nodig heeft.',
            'empty_state_heading' => 'De bestanden kunnen nu niet worden getoond',
        ],
    ],
    'besluiten' => [
        // Shown when a document belonging to a besluit cannot be fetched. Has
        // its own wording on purpose: a besluit is only shown once it carries an
        // established document, so a missing file can mean the besluit itself is
        // not on screen, which is a different thing to a reader than a file
        // missing from the file list.
        'unreadable' => [
            'title' => '{1} Eén bestand bij een besluit kan nu niet worden getoond|[2,*] :count bestanden bij besluiten kunnen nu niet worden getoond',
            'description' => 'Dit ligt niet aan uw aanvraag. Een besluit kan hierdoor onvolledig zijn of nog niet zichtbaar. Probeer het later opnieuw, of neem contact op met de beheerder.',
        ],
    ],
    'filters' => [
        'workingstock' => [
            'label' => 'Snelfilter werkvoorraad',
            'options' => [
                'me' => 'Mijn werkvoorraad',
                'new' => 'Nieuw',
                'all' => 'Alle zaken',
                'all_eventloket' => 'Alle zaken binnen Eventloket',
            ],
        ],
    ],
    'navigation_groups' => [
        'with_advice_thread' => 'Zaken met adviesvraag',
        'all' => 'Eventloket zaken',
    ],
    'actions' => [
        'delete_zaak' => [
            'label' => 'Verwijder zaak',
            'confirmation' => [
                'title' => 'Weet je zeker dat je deze zaak wilt verwijderen?',
                'description' => 'De zaak wordt soft-deleted en is alleen nog zichtbaar voor platformbeheerders. Je kunt de zaak later herstellen via het verwijderd filter. LET OP: Als je de zaak ook in OpenZaak verwijdert, kan deze NIET meer hersteld worden!',
            ],
            'checkbox' => [
                'delete_in_openzaak' => 'Ook verwijderen in OpenZaak (PERMANENTE ACTIE - kan niet ongedaan gemaakt worden)',
            ],
            'success' => 'Zaak succesvol verwijderd',
            'unauthorized' => 'U bent niet geautoriseerd om deze zaak te verwijderen.',
            'error_open_zaak' => 'Zaak is soft-deleted in Eventloket, maar kon niet verwijderd worden in OpenZaak.',
        ],
        'restore_zaak' => [
            'label' => 'Herstel zaak',
            'success' => 'Zaak succesvol hersteld',
        ],
    ],
];
