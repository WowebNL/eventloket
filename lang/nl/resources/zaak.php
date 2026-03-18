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
        'registratiedatum' => [
            'label' => 'Registratiedatum',
        ],
        'zaaktype' => [
            'label' => 'Zaaktype',
        ],
        'risico_classificatie' => [
            'label' => 'Risicoclassificatie',
        ],
        'start_evenement' => [
            'label' => 'Start evenement',
        ],
        'eind_evenement' => [
            'label' => 'Eind evenement',
        ],
        'telefoon' => [
            'label' => 'Telefoonnummer organisatie',
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
        'resultaat' => [
            'label' => 'Resultaat',
        ],
        'municipality' => [
            'label' => 'Gemeente',
        ],
        'naam_locatie_evenement' => [
            'label' => 'Naam locatie evenement',
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
