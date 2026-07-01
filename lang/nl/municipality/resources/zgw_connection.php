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
        'vertrouwelijkheid' => [
            'heading' => 'Vertrouwelijkheid',
            'description' => 'Bepaal per rol welke vertrouwelijkheidsniveaus zichtbaar zijn en welk niveau standaard bij uploaden wordt gebruikt. Laat een rol leeg om de standaardwaarden van Eventloket aan te houden. De rol-gebaseerde filtering blijft altijd actief.',
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
        'vertrouwelijkheid_visibility' => [
            'label' => 'Zichtbare niveaus',
            'helper' => 'De vertrouwelijkheidsniveaus die deze rol mag zien. Leeg laten valt terug op de standaard.',
        ],
        'vertrouwelijkheid_upload_default' => [
            'label' => 'Standaard bij uploaden',
            'helper' => 'Het niveau dat vooraf is ingevuld wanneer deze rol een document uploadt. Leeg laten valt terug op de standaard.',
        ],
        'vertrouwelijkheid_system_default' => [
            'label' => 'Standaard voor systeemdocumenten',
            'helper' => 'Het niveau voor automatisch gegenereerde documenten (de aanvraag-PDF en de formulier-bijlagen). Leeg laten valt terug op zaakvertrouwelijk.',
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

    'vertrouwelijkheid_groups' => [
        'gemeente' => 'Gemeente (behandelaars en beheerders)',
    ],

    'vertrouwelijkheid_levels' => [
        'openbaar' => 'Openbaar',
        'beperkt_openbaar' => 'Beperkt openbaar',
        'intern' => 'Intern',
        'zaakvertrouwelijk' => 'Zaakvertrouwelijk',
        'vertrouwelijk' => 'Vertrouwelijk',
        'confidentieel' => 'Confidentieel',
        'geheim' => 'Geheim',
        'zeer_geheim' => 'Zeer geheim',
    ],

    'columns' => [
        'name' => ['label' => 'Naam'],
        'zaken_url' => ['label' => 'Zaken API'],
        'version' => ['label' => 'Versie'],
        'last_verified_at' => ['label' => 'Laatste controle'],
        'activated_at' => [
            'label' => 'Status',
            'active' => 'Actief',
            'inactive' => 'Inactief',
        ],
        'updated_at' => ['label' => 'Laatst gewijzigd'],
    ],

    'actions' => [
        'verify' => [
            'label' => 'Verbinding testen',
            'modal_heading' => 'Verbinding controleren',
            'close' => 'Sluiten',
            'steps' => [
                'connection' => 'Verbinding met de ZGW-instantie',
                'abonnement' => 'Notificatie-abonnement',
            ],
            'connection' => [
                'success' => 'Eventloket kan deze ZGW-instantie bereiken.',
                'error' => 'Kon de verbinding niet controleren. Probeer het later opnieuw of neem contact op met de beheerder.',
            ],
            'abonnement' => [
                'healthy' => 'Het abonnement is geregistreerd en werkt.',
                'expiring_soon' => 'Het abonnement werkt. Het token verloopt binnenkort en wordt automatisch vernieuwd.',
                'needs_register' => 'Er is nog geen geldig abonnement geregistreerd.',
                'no_notificaties_url' => 'Deze koppeling heeft geen Notificaties API-URL, dus er kan geen abonnement geregistreerd worden.',
                'register' => 'Abonnement registreren',
                'error' => 'Er ging iets mis bij het controleren of registreren van het abonnement. Probeer het later opnieuw.',
            ],
            'result' => [
                'success' => 'De verbinding is volledig gecontroleerd en werkt.',
                'fail' => 'De controle is niet volledig geslaagd. Zie de stappen hierboven.',
            ],
        ],
        'activate' => [
            'label' => 'Activeren',
            'requires_verification' => 'Test eerst de verbinding voordat je deze koppeling activeert.',
            'modal_heading' => 'Koppeling activeren',
            'modal_description' => 'Vanaf nu worden zaken van deze gemeente via deze ZGW-instantie verwerkt.',
            'confirm' => 'Activeren',
            'success' => 'De koppeling is geactiveerd.',
        ],
        'deactivate' => [
            'label' => 'Deactiveren',
            'modal_heading' => 'Koppeling deactiveren',
            'modal_description' => 'Zaken van deze gemeente vallen terug op de standaard ZGW-instantie tot de koppeling opnieuw wordt geactiveerd.',
            'confirm' => 'Deactiveren',
            'success' => 'De koppeling is gedeactiveerd.',
        ],
    ],

];
