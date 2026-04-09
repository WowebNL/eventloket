<?php

return [
    'label' => 'Gemeente',
    'plural_label' => 'Gemeenten',

    'columns' => [

        'name' => [
            'label' => 'Naam',
        ],
        'brk_identification' => [
            'label' => 'BRK (kadaster) Identificatie',
            'helper_text' => 'De BRK identificatie moet beginnen met "GM".',
        ],
        'zaaktypen' => [
            'label' => 'Zaaktypen',
        ],
        'doorkomst_zaaktype_id' => [
            'label' => 'Doorkomst zaaktype',
            'helper_text' => 'Het zaaktype dat gebruikt wordt voor deelzaken wanneer een evenementenroute door deze gemeente passeert.',
        ],
    ],
];
