<?php

return [
    'navigation_label' => 'Homepagina',
    'heading' => 'Homepagina instellingen',

    'form' => [
        'title' => [
            'label' => 'Titel',
        ],
        'tagline' => [
            'label' => 'Tagline',
            'default' => 'Snel en eenvoudig uw evenement regelen',
        ],
        'welcome_image' => [
            'label' => 'Afbeelding op de homepagina',
        ],
        'intro' => [
            'label' => 'Introductie tekst',
            'default' => 'Welkom bij onze evenement applicatie!',
        ],
        'usps' => [
            'label' => 'Unique Selling Points',
            'add_action_label' => 'Nieuwe USP toevoegen',
            'items' => [
                'icon' => [
                    'label' => 'Icoon',
                ],
                'title' => [
                    'label' => 'Titel',
                ],
                'description' => [
                    'label' => 'Beschrijving',
                ],
            ],
        ],
        'outro' => [
            'label' => 'Outro tekst',
            'helper_text' => 'Dit is de tekst onder de usps',
        ],
        'faqs' => [
            'label' => 'Veelgestelde vragen',
            'add_action_label' => 'Nieuwe veelgestelde vraag toevoegen',
            'items' => [
                'question' => [
                    'label' => 'Vraag',
                ],
                'answer' => [
                    'label' => 'Antwoord',
                ],
            ],
        ],
    ],
];
