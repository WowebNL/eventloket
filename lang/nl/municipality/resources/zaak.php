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
        'organisator' => [
            'label' => 'Organisator',
        ],
        'organisation' => [
            'label' => 'Organisatie',
        ],
        'uiterlijkeEinddatumAfdoening' => [
            'label' => 'Uiterlijke einddatum afdoening',
        ],
    ],
    'header_actions' => [
        'finish_zaak' => [
            'label' => 'Zaak afronden',
            'steps' => [
                'result' => [
                    'label' => 'Resultaat',
                    'schema' => [
                        'result_type' => [
                            'label' => 'Resultaattype',
                        ],
                        'result_has_besluit' => [
                            'label' => 'Bevat besluit',
                            'helper_text' => 'Geeft aan of het geselecteerde resultaattype een besluit vereist.',
                        ],
                        'result_toelichting' => [
                            'label' => 'Toelichting op het resultaat',
                            'helper_text' => 'De toelichting wordt opgeslagen bij het resultaat en gedeeld met de betrokkenen.',
                        ],
                        'besluit_type' => [
                            'label' => 'Type besluit',
                        ],
                        'datum_besluit' => [
                            'label' => 'De beslisdatum (AWB) van het besluit',
                        ],
                        'besluit_documenten' => [
                            'label' => 'Document(en) die geregistreerd worden bij het besluit',
                            'helper_text' => 'Wanneer de optie lijst leeg is heb je waarschijnlijk nog geen document met het type besluit toegevoegd aan de zaak. Voeg eerst een document toe met het juiste type voordat je de zaak afrondt.',
                        ],
                        'besluit_toelichting' => [
                            'label' => 'Toelichting bij het besluit',
                            'helper_text' => 'De toelichting wordt opgeslagen bij het besluit en gedeeld met de betrokkenen.',
                        ],
                        'ingangsdatum' => [
                            'label' => 'Ingangsdatum van de werkingsperiode van het besluit',
                        ],
                        'vervaldatum' => [
                            'label' => 'Datum waarop de werkingsperiode van het besluit eindigt',
                        ],
                        'message_title' => [
                            'label' => 'Titel van het bericht',
                        ],
                        'message_content' => [
                            'label' => 'Inhoud van het bericht',
                        ],
                        'message_documenten' => [
                            'label' => 'Document(en) die toegevoegd worden bij het bericht',
                            'helper_text' => 'Voeg minimaal het besluit toe aan het bericht.',
                        ],
                        'submit' => [
                            'label' => 'Zaak afronden',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'infolist' => [
        'sections' => [
            'information' => [
                'label' => 'Informatie',
                'description' => 'Informatie over de zaak',
            ],
            'actions' => [
                'label' => 'Acties',
                'description' => 'Voer wijzigingen uit binnen de zaak',
                'actions' => [
                    'edit_risico_classificatie' => [
                        'label' => 'Wijzigen',
                    ],
                ],
            ],
        ],
        'tabs' => [
            'decisions' => [
                'label' => 'Besluiten',
            ],
            'documents' => [
                'label' => 'Bestanden',
            ],
            'messages' => [
                'label' => 'Organisatievragen',
            ],
            'advice_requests' => [
                'label' => 'Adviesvragen',
            ],
            'locations' => [
                'label' => 'Locaties',
                'information' => [
                    'label' => 'Informatie',
                    'address' => [
                        'label' => 'Adres',
                    ],
                    'location_name' => [
                        'label' => 'Locatienaam',
                    ],
                ],
                'map' => [
                    'label' => 'Kaart',
                ],
            ],
            'log' => [
                'label' => 'Log',
            ],
        ],
    ],
];
