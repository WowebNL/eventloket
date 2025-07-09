<?php

return [
    'label' => 'User',
    'plural_label' => 'Users',

    'columns' => [

        'name' => [
            'label' => 'Name',
        ],

        'admin' => [
            'label' => 'Admin',
        ],

    ],

    'actions' => [
        'invite' => [
            'label' => 'Invite user',
            'modal_submit_action_label' => 'Send invite',
            'form' => [
                'email' => [
                    'label' => 'Email address',
                ],
                'make_admin' => [
                    'label' => 'Make admin',
                    'helper_text' => 'Admins will be able to manage the organisation details and invite new users.',
                ],
            ],
            'notification' => [
                'title' => 'Invite sent',
            ],
        ],
    ],
];
