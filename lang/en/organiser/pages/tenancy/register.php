<?php

return [

    'label' => 'Register organisation',

    'form' => [

        'name' => [
            'label' => 'Name',
        ],

        'coc_number' => [
            'label' => 'CoC number',
            'validation' => [
                'unique' => 'An organization with this :attribute already exists in our system. Please contact the organization to request access.',
            ],
        ],

        'address' => [
            'label' => 'Address',
        ],

        'email' => [
            'label' => 'General email address',
        ],

        'phone' => [
            'label' => 'General phone number',
        ],

    ],

];
