<?php

return [
    'label' => 'Vernietigingslijst',
    'plural_label' => 'Vernietigingslijsten',

    'columns' => [
        'name' => [
            'label' => 'Naam',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'items_count' => [
            'label' => 'Aantal zaken',
        ],
        'created_by' => [
            'label' => 'Aangemaakt door',
        ],
        'created_at' => [
            'label' => 'Aangemaakt op',
        ],
        'reviewed_by' => [
            'label' => 'Beoordeeld door',
        ],
        'reviewed_at' => [
            'label' => 'Beoordeeld op',
        ],
        'review_feedback' => [
            'label' => 'Feedback beoordelaar',
        ],
        'confirmed_at' => [
            'label' => 'Vernietiging bevestigd op',
        ],
        'coordinator_name' => [
            'label' => 'Bevestigd door',
        ],
        'destruction_method' => [
            'label' => 'Wijze van vernietiging',
        ],
        'report' => [
            'label' => 'Vernietigingsrapport',
        ],
    ],

    'items' => [
        'label' => 'Zaak',
        'plural_label' => 'Te vernietigen zaken',
        'columns' => [
            'zaaknummer' => [
                'label' => 'Zaaknummer',
            ],
            'naam_evenement' => [
                'label' => 'Evenement',
            ],
            'zaaktype_naam' => [
                'label' => 'Zaaktype',
            ],
            'archiefactiedatum' => [
                'label' => 'Archiefactiedatum',
            ],
            'selectielijst_categorie' => [
                'label' => 'Grondslag',
            ],
            'bewaartermijn' => [
                'label' => 'Bewaartermijn',
            ],
            'status' => [
                'label' => 'Status',
            ],
            'failure_reason' => [
                'label' => 'Reden mislukt/overgeslagen',
            ],
        ],
    ],

    'actions' => [
        'add_zaken' => [
            'label' => 'Zaken toevoegen',
            'form' => [
                'zaak_ids' => [
                    'label' => 'Vernietigbare zaken',
                    'helper_text' => 'Alleen zaken met archiefnominatie "vernietigen" en een verstreken archiefactiedatum worden getoond.',
                ],
            ],
            'notification' => [
                'title' => ':count zaken toegevoegd aan de lijst',
            ],
            'empty_notification' => [
                'title' => 'Geen vernietigbare zaken gevonden',
            ],
        ],
        'submit_for_review' => [
            'label' => 'Ter beoordeling aanbieden',
            'modal_description' => 'De lijst wordt ter beoordeling aangeboden aan de archiefbeoordelaars van de gemeente.',
            'notification' => [
                'title' => 'Lijst aangeboden ter beoordeling',
            ],
            'empty_notification' => [
                'title' => 'De lijst bevat geen zaken',
            ],
        ],
        'approve' => [
            'label' => 'Akkoord',
            'modal_description' => 'Je geeft akkoord op de vernietiging van alle zaken op deze lijst. De archiefcoördinator kan de vernietiging daarna definitief bevestigen.',
            'notification' => [
                'title' => 'Lijst akkoord bevonden',
            ],
        ],
        'request_changes' => [
            'label' => 'Wijzigingen vragen',
            'form' => [
                'review_feedback' => [
                    'label' => 'Feedback',
                ],
            ],
            'notification' => [
                'title' => 'Feedback verstuurd naar de archiefcoördinator',
            ],
        ],
        'confirm_destruction' => [
            'label' => 'Vernietiging bevestigen',
            'modal_heading' => 'Vernietiging definitief bevestigen',
            'modal_description' => 'Let op: dit kan niet ongedaan worden gemaakt. Alle zaken op deze lijst worden permanent vernietigd in OpenZaak en Eventloket, inclusief documenten, besluiten en berichten.',
            'form' => [
                'confirmation' => [
                    'label' => 'Typ de naam van de lijst ter bevestiging',
                    'validation' => 'De ingevoerde naam komt niet overeen met de naam van de lijst.',
                ],
                'coordinator_function' => [
                    'label' => 'Jouw functie',
                ],
                'destruction_method' => [
                    'label' => 'Wijze van vernietiging',
                ],
            ],
            'notification' => [
                'title' => 'Vernietiging gestart',
            ],
        ],
        'retry' => [
            'label' => 'Vernietiging opnieuw proberen',
            'modal_description' => 'De mislukte zaken op deze lijst worden opnieuw vernietigd.',
            'notification' => [
                'title' => 'Vernietiging opnieuw gestart',
            ],
        ],
    ],
];
