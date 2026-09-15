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
        // Shown when the documents API hands over the list of a zaak but does not
        // hand over one or more of the documents themselves, for a reason that may
        // pass on its own: a server error, a timeout. Deliberately says nothing
        // technical: the reader can do nothing with a status code, only with the
        // fact that something is missing, that it is not their doing, and that
        // trying again later is worth it.
        'unavailable' => [
            'title' => '{1} Eén bestand kan nu niet worden getoond|[2,*] :count bestanden kunnen nu niet worden getoond',
            'description' => 'Dit ligt niet aan uw aanvraag. Probeer het later opnieuw, of neem contact op met de beheerder als u een bestand nu nodig heeft.',
            'empty_state_heading' => 'De bestanden kunnen nu niet worden getoond',
        ],
        // Shown when the documents API is not authorised to hand a document over.
        // Two things set this apart from the wording above, and both are the point
        // of it. There is no "try again later", because the answer will be the same
        // tomorrow. And there is no number, because the reader is not shown the
        // documents the authorisation excludes and a count would tell them how many
        // of those exist.
        'forbidden' => [
            'title' => 'Niet beschikbaar via deze koppeling',
            'description' => 'Deze zaak bevat bestanden die niet via Eventloket opgehaald mogen worden. Neem contact op met de beheerder als u een van deze bestanden nodig heeft.',
            'empty_state_heading' => 'De bestanden zijn niet beschikbaar via deze koppeling',
        ],
    ],
    'besluiten' => [
        // The besluiten halves of the same two messages. They have their own
        // wording on purpose: a besluit is only shown once it carries an
        // established document, so a missing file can mean the besluit itself is
        // not on screen, which is a different thing to a reader than a file
        // missing from the file list.
        'unavailable' => [
            'title' => '{1} Eén bestand bij een besluit kan nu niet worden getoond|[2,*] :count bestanden bij besluiten kunnen nu niet worden getoond',
            'description' => 'Dit ligt niet aan uw aanvraag. Een besluit kan hierdoor onvolledig zijn of nog niet zichtbaar. Probeer het later opnieuw, of neem contact op met de beheerder.',
        ],
        'forbidden' => [
            'title' => 'Niet beschikbaar via deze koppeling',
            'description' => 'Bij een besluit van deze zaak horen bestanden die niet via Eventloket opgehaald mogen worden. Een besluit kan daardoor onvolledig zijn of niet zichtbaar. Neem contact op met de beheerder als u deze bestanden nodig heeft.',
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
