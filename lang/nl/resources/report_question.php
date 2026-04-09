<?php

return [
    'label' => 'Meldingvraag',
    'plural_label' => 'Meldingvragen',
    'table' => [
        'description' => 'Hier kunnen de vragen beheerd worden die in het formulier aan de organisator worden gesteld om te bepalen of voor jouw gemeente een melding voldoende is of dat er een vergunning nodig is. Het is belangrijk dat iedere vraag met ja of nee te beantwoorden is. Wanneer een organisator één van de vragen met nee beantwoordt, is altijd een vergunning nodig. Wanneer een organisator een vraag met ja beantwoordt, dan zal het systeem de volgende meldingvraag stellen tot deze allen met ja zijn beantwoord, in dat geval is een melding voldoende en zullen in het formulier de vergunningvragen niet getoond worden. In het formulier worden alleen de vragen getoond die actief zijn. De volgorde van de vragen kan worden aangepast door te klikken op "Volgorde aanpassen", vervolgens kunnen de vragen versleept worden naar de gewenste positie. Daarna kan op "Volgorde opslaan" worden geklikt om de nieuwe volgorde op te slaan.',
    ],

    'columns' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
        ],

        'is_active' => [
            'label' => 'Actief',
        ],

        'created_at' => [
            'label' => 'Aangemaakt op',
        ],
        'updated_at' => [
            'label' => 'Bijgewerkt op',
        ],

    ],
    'actions' => [
        'create' => [
            'label' => 'Variabele aanmaken',
        ],
        'disable_reordering' => [
            'label' => 'Volgorde opslaan',
        ],
        'enable_reordering' => [
            'label' => 'Volgorde aanpassen',
        ],
    ],

    'form' => [

        'order' => [
            'label' => 'Volgorde',
        ],

        'question' => [
            'label' => 'Vraag',
            'helper_text' => 'De meldingvraag tekst zoals deze getoond moet worden in het formulier. Zorg ervoor dat de vraag met ja of nee beantwoord kan worden. Ja betekent dat er een melding voldoende is (als alle vragen met ja zijn beantwoord), nee betekent dat er een vergunning nodig is.',
        ],

        'is_active' => [
            'label' => 'Actief',
            'helper_text' => 'Alleen actieve vragen worden getoond in het formulier. Wanneer een vraag inactief wordt, zal deze niet meer getoond worden in het formulier en zal deze niet meer meegenomen worden in de beoordeling of een melding voldoende is of dat er een vergunning nodig is.',
        ],
    ],
];
