<?php

return [
    'label' => 'Locatie',
    'plural_label' => 'Locaties',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],

        'address' => [
            'label' => 'Adres',
        ],

        'postal_code' => [
            'label' => 'Postcode',
        ],

        'house_number' => [
            'label' => 'Huisnummer',
        ],

        'house_letter' => [
            'label' => 'Huisletter',
        ],

        'house_number_addition' => [
            'label' => 'Huisnummertoevoeging',
        ],

        'street_name' => [
            'label' => 'Straatnaam',
        ],

        'city_name' => [
            'label' => 'Woonplaatsnaam',
        ],

        'active' => [
            'label' => 'Actief',
        ],

        'bagid' => [
            'label' => 'Identificatie in het Basisregistratie Adressen en Gebouwen (BAG)',
        ],

    ],

    'actions' => [
        'create' => [
            'label' => 'Locatie aanmaken',
        ],
    ],

    'form' => [

        'name' => [
            'label' => 'Naam',
        ],

        'postal_code' => [
            'label' => 'Postcode',
        ],

        'house_number' => [
            'label' => 'Huisnummer',
        ],

        'house_letter' => [
            'label' => 'Huisletter',
        ],

        'house_number_addition' => [
            'label' => 'Huisnummertoevoeging',
        ],

        'street_name' => [
            'label' => 'Straatnaam',
        ],

        'city_name' => [
            'label' => 'Woonplaatsnaam',
        ],

        'active' => [
            'label' => 'Actief',
            'helper_text' => 'Geeft aan of de locatie open staat voor nieuwe aanvragen.',
        ],

        'geometry' => [
            'label' => 'Locatie',
            'validation' => [
                'geojson_required' => 'Plaats minimaal één geometrie op de kaart.',
            ],
        ],

        'bagid' => [
            'label' => 'Identificatie in het Basisregistratie Adressen en Gebouwen (BAG)',
        ],

    ],
];
