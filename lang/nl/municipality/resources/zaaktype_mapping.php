<?php

return [

    'label' => 'Zaaktype-koppeling',
    'plural_label' => 'Zaaktype-koppelingen',

    'sections' => [
        'zaaktype' => [
            'heading' => 'Zaaktype',
            'description' => 'Kies per Eventloket-rol het bijbehorende zaaktype uit de catalogi van de gekoppelde ZGW-instantie. Laat een rol leeg om de naam-conventie als terugval te gebruiken.',
        ],
        'eigenschappen' => [
            'heading' => 'Eigenschappen',
            'description' => 'Koppel elke logische Eventloket-sleutel aan de bijbehorende eigenschap-naam in dit zaaktype.',
        ],
        'flow' => [
            'heading' => 'Flow-blockers',
            'description' => 'De statustypen, het initiator-roltype, het Ingetrokken-resultaattype en het bijlage-documenttype voor dit zaaktype.',
        ],
        'behaviour' => [
            'heading' => 'Gedrag',
            'description' => 'Instellingen die per gemeente gelden voor dit zaaktype in de eigen ZGW-instantie.',
        ],
    ],

    'fields' => [
        'role' => ['label' => 'Zaaktype Eventloket'],
        'zaaktype_identificatie' => ['label' => 'Zaaktype ZGW'],
        'triggers_route_check' => [
            'label' => 'Route-check activeren',
            'helper_text' => 'Als aangevinkt, wordt na aanmaken van een zaak gecontroleerd of de route door andere gemeenten gaat en worden er automatisch deelzaken aangemaakt.',
        ],
        'hidden_resultaat_types' => [
            'label' => 'Te verbergen resultaten',
            'helper_text' => 'Selecteer welke resultaten (bijv. "Ingetrokken") niet zichtbaar moeten zijn in de kalender en lijstweergave.',
        ],
        'initial_statustype' => ['label' => 'Begin-statustype'],
        'eind_statustype' => ['label' => 'Eind-statustype'],
        'initiator_roltype' => ['label' => 'Initiator-roltype'],
        'ingetrokken_resultaattype' => ['label' => 'Ingetrokken-resultaattype'],
        'aanvraag_informatieobjecttype' => ['label' => 'Aanvraag-documenttype'],
        'bijlage_informatieobjecttype' => ['label' => 'Bijlage-documenttype'],
    ],

    'columns' => [
        'role' => ['label' => 'Zaaktype Eventloket'],
        'zaaktype_identificatie' => ['label' => 'Zaaktype ZGW'],
        'updated_at' => ['label' => 'Laatst gewijzigd'],
    ],

    'placeholder' => 'Erf van naam-conventie',

];
