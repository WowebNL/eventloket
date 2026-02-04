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
        'change_zaaktype' => [
            'label' => 'Zaaktype wijzigen',
            'modal_heading' => 'Zaaktype wijzigen',
            'modal_description' => 'Let op: het wijzigen van het zaaktype kan invloed hebben op de verdere afhandeling van de zaak.',
            'modal_submit_label' => 'Wijzigen',
            'form' => [
                'new_zaaktype_id' => [
                    'label' => 'Nieuw zaaktype',
                    'helper_text' => 'Selecteer het nieuwe zaaktype voor deze zaak.',
                ],
            ],
            'notifications' => [
                'success' => [
                    'title' => 'Zaaktype gewijzigd',
                    'body' => 'Het zaaktype is succesvol gewijzigd naar \':zaaktype\'.',
                ],
                'error' => [
                    'title' => 'Fout bij wijzigen zaaktype',
                    'body' => 'Er is een fout opgetreden bij het wijzigen van het zaaktype: :error',
                ],
            ],
        ],
    ],
];
