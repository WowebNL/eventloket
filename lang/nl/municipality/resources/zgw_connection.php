<?php

return [

    'label' => 'ZGW-koppeling',
    'plural_label' => 'ZGW-koppeling',

    'sections' => [
        'endpoints' => [
            'heading' => 'Endpoints',
            'description' => 'De volledige base-URL per ZGW-API, inclusief versiepad en afsluitende slash. Laat een veld leeg om de waarde van de hoofdkoppeling te erven.',
        ],
        'authentication' => [
            'heading' => 'Authenticatie',
            'description' => 'De inloggegevens waarmee Eventloket bij deze ZGW-instantie verbindt.',
        ],
        'parameters' => [
            'heading' => 'Technische parameters',
            'description' => 'Instance-specifieke instellingen. Laat leeg om het gedrag van de hoofdkoppeling te erven.',
        ],
        'features' => [
            'heading' => 'Eventloket functies',
            'description' => 'Bepaal hoe Eventloket zaken van deze koppeling toont en notificeert. De standaardwaarden houden het volledige gedrag aan.',
        ],
    ],

    'fields' => [
        'name' => [
            'label' => 'Naam',
            'helper' => 'Optioneel label ter herkenning (bijv. de leverancier en gemeente). Heeft geen invloed op de werking.',
        ],
        'zaken_url' => ['label' => 'Zaken API base-URL'],
        'catalogi_url' => ['label' => 'Catalogi API base-URL'],
        'documenten_url' => ['label' => 'Documenten API base-URL'],
        'besluiten_url' => ['label' => 'Besluiten API base-URL'],
        'autorisaties_url' => ['label' => 'Autorisaties API base-URL'],
        'notificaties_url' => ['label' => 'Notificaties API base-URL'],
        'version' => ['label' => 'ZGW-versie'],
        'client_id' => ['label' => 'Client ID'],
        'client_secret' => [
            'label' => 'Client secret',
            'helper_create' => 'Minimaal 32 tekens.',
            'helper_edit' => 'Minimaal 32 tekens. Laat leeg om de bestaande secret ongewijzigd te laten.',
        ],
        'user_id' => ['label' => 'User ID'],
        'user_representation' => ['label' => 'User representation'],
        'allowed_hosts' => [
            'label' => 'Toegestane hosts',
            'helper' => 'Extra origins (naast de zes base-URLs) waar deze koppeling documenten mag ophalen.',
        ],
        'bronorganisatie_rsin' => [
            'label' => 'Bronorganisatie RSIN',
            'helper' => 'RSIN die als bronorganisatie op elke zaak wordt gezet.',
        ],
        'eigenschap_date_format' => [
            'label' => 'Datumformaat zaakeigenschappen',
            'helper' => 'Optioneel PHP-datumformaat voor zaakeigenschap-waarden (bijv. YmdHis). Leeg laten houdt het formulier-formaat aan.',
        ],
        'lock_status_for_behandelaar' => [
            'label' => 'Status niet wijzigbaar door behandelaar',
            'helper' => 'De behandelaar kan de status niet wijzigen en de zaak niet afronden in Eventloket. Intrekken door de organisator blijft mogelijk.',
        ],
        'show_besluiten_tab' => [
            'label' => 'Tabblad besluiten tonen',
            'helper' => 'Toon het tabblad besluiten bij een zaak.',
        ],
        'show_bestanden_tab' => [
            'label' => 'Tabblad bestanden tonen',
            'helper' => 'Toon het tabblad bestanden. Bij uitschakelen ziet de organisator nog wel de eigen aanvraag-bestanden, maar kunnen er geen nieuwe bestanden bijkomen.',
        ],
        'show_adviesvragen_tab' => [
            'label' => 'Tabblad adviesvragen tonen',
            'helper' => 'Toon het tabblad adviesvragen bij een zaak.',
        ],
        'show_organisatievragen_tab' => [
            'label' => 'Tabblad organisatievragen tonen',
            'helper' => 'Toon het tabblad organisatievragen bij een zaak.',
        ],
        'suppress_notifications' => [
            'label' => 'Geen notificaties versturen',
            'helper' => 'Onderdruk alle notificaties voor een zaak. Alleen de ontvangstbevestiging bij indienen wordt nog verstuurd.',
        ],
    ],

    'columns' => [
        'name' => ['label' => 'Naam'],
        'zaken_url' => ['label' => 'Zaken API'],
        'version' => ['label' => 'Versie'],
        'updated_at' => ['label' => 'Laatst gewijzigd'],
    ],

    'actions' => [
        'test' => [
            'label' => 'Verbinding testen',
            'success' => 'Verbinding geslaagd',
            'success_body' => 'Eventloket kon deze ZGW-instantie bereiken.',
            'failure' => 'Verbinding mislukt',
        ],
    ],

];
