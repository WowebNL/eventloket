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
            'description' => 'Bepaal per rolgroep het maximaal zichtbare vertrouwelijkheidsniveau en welk niveau standaard bij uploaden wordt gebruikt. Laat een rolgroep leeg om de standaardwaarden van Eventloket aan te houden. De rol-gebaseerde filtering blijft altijd actief.',
            'explanation' => [
                'title' => 'Zo werkt de vertrouwelijkheid-instelling',
                'intro' => 'Per rolgroep kiest u één maximaal zichtbaar niveau. Alle opener niveaus zijn daarbij automatisch inbegrepen: staat de gemeente op "Intern", dan ziet de gemeente ook "Beperkt openbaar" en "Openbaar", maar niets daarboven. Dit volgt de ZGW-standaard, die een maximale vertrouwelijkheidaanduiding inclusief opvat over de schaal openbaar, beperkt openbaar, intern, zaakvertrouwelijk, vertrouwelijk, confidentieel, geheim, zeer geheim.',
                'nesting' => 'Houd de maxima oplopend: het maximum van de organisator mag niet hoger liggen dan dat van de adviseur, en dat van de adviseur niet hoger dan dat van de gemeente. Zo vormen de rolgroepen geneste doelgroepen (organisator binnen adviseur binnen gemeente). Een instelling die daarvan afwijkt wordt bij het opslaan geweigerd.',
                'upload' => 'Dezelfde maxima bepalen wat een behandelaar bij het uploaden kan kiezen. Elk ingesteld maximum is één keuze in de lijst "Wie mag dit document inzien?", met daarbij de rolgroepen die dat niveau mogen zien. Maken de maxima geen onderscheid tussen de rolgroepen, dan verdwijnt die keuze.',
                'default' => '"Standaard bij uploaden" is de terugvaloptie: dit niveau geldt wanneer er geen keuze wordt gemaakt, bijvoorbeeld bij uploads door de organisator of wanneer de maxima geen onderscheid tussen de rolgroepen maken.',
                'openbaar' => '"Openbaar" is het meest open niveau en valt dus altijd binnen elk ingesteld maximum. Documenten die automatisch op openbaar worden gezet, zoals systeemuploads vanuit het zaaksysteem, zijn op een eigen koppeling met ingestelde maxima daarom voor elke rolgroep zichtbaar.',
            ],
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
        'url_inheritance' => [
            'with_url' => 'Laat u dit veld leeg, dan wordt :url van de hoofdkoppeling gebruikt. Gegevens worden dan bij die instantie opgevraagd en blijven hier leeg.',
            'without_url' => 'Laat u dit veld leeg, dan wordt de URL van de hoofdkoppeling gebruikt. Gegevens worden dan bij die instantie opgevraagd en blijven hier leeg.',
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
            'helper' => 'RSIN die als bronorganisatie op elke zaak wordt gezet. Bepaalt ook aan welke koppeling een binnenkomende notificatie wordt toegewezen: deelt deze koppeling een host met andere koppelingen, vul dan altijd een eigen RSIN in. Leeg laten erft de standaardwaarde van de hoofdkoppeling en maakt die toewijzing onbetrouwbaar.',
        ],
        'vertrouwelijkheid_visibility' => [
            'label' => 'Maximaal zichtbaar niveau',
            'helper' => 'Het hoogste vertrouwelijkheidsniveau dat deze rolgroep mag zien. Alle opener niveaus zijn automatisch inbegrepen. Leeg laten valt terug op de standaard.',
            'tooltip' => 'Een maximum is inclusief: kiest u "Intern", dan ziet deze rolgroep ook "Beperkt openbaar" en "Openbaar", maar niets daarboven.',
            'nesting_error' => 'Het maximum van :broader mag niet lager liggen dan dat van :narrower. Houd de doelgroepen oplopend genest: organisator binnen adviseur binnen gemeente.',
        ],
        'vertrouwelijkheid_upload_default' => [
            'label' => 'Standaard bij uploaden',
            'helper' => 'Het niveau dat vooraf is ingevuld wanneer deze rolgroep een document uploadt. Leeg laten valt terug op de standaard.',
            'tooltip' => 'Terugvaloptie: dit niveau wordt gebruikt als er geen keuze wordt gemaakt, bijvoorbeeld bij uploads door de organisator of wanneer de ingestelde maxima geen onderscheid tussen de rolgroepen maken.',
        ],
        'vertrouwelijkheid_system_default' => [
            'label' => 'Standaard voor systeemdocumenten',
            'helper' => 'Het niveau voor automatisch gegenereerde documenten (de aanvraag-PDF en de formulier-bijlagen). Leeg laten valt terug op zaakvertrouwelijk.',
        ],
        'lock_status_for_behandelaar' => [
            'label' => 'Status niet wijzigbaar door behandelaar',
            'helper' => 'De behandelaar kan de status niet wijzigen en de zaak niet afronden in Eventloket. Intrekken door de organisator wordt apart geregeld met de instelling hieronder.',
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
        'allow_organiser_withdrawal' => [
            'label' => 'Intrekken door organisator toestaan',
            'helper' => 'Sta toe dat een organisator een aanvraag intrekt via Eventloket. Niet beschikbaar voor een OneGround (RX Mission) koppeling: daar mislukt het intrekken omdat het zetten van de eindstatus de zaak meteen archiveert, wat pas mag als alle documenten gearchiveerd zijn.',
        ],
        'is_oneground' => [
            'label' => 'Dit is een OneGround koppeling',
            'helper' => 'Zet dit aan voor een OneGround (RX Mission) koppeling. OneGround wijkt op punten af van de ZGW-standaard: de globale locatie wordt als kale tekst meegestuurd in plaats van als object, en intrekken door de organisator wordt geblokkeerd.',
        ],
    ],

    'vertrouwelijkheid_groups' => [
        'gemeente' => 'Gemeente (behandelaars en beheerders)',
        // Short form, used where the group is named as an audience ("wie mag dit
        // document inzien?") instead of as a form heading.
        'gemeente_audience' => 'Gemeente',
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
                'apis' => 'Toegang tot de losse APIs',
                'abonnement' => 'Notificatie-abonnement',
            ],
            'connection' => [
                'success' => 'Eventloket kan deze ZGW-instantie bereiken.',
                'error' => 'Kon de verbinding niet controleren. Probeer het later opnieuw of neem contact op met de beheerder.',
            ],
            'apis' => [
                'success' => 'Zaken, documenten en besluiten zijn alle drie leesbaar.',
                'error' => 'Deze APIs zijn niet leesbaar: :apis. Controleer de URL en de autorisatie. Let op: een leeg URL-veld neemt de URL van de hoofdkoppeling over, waardoor gegevens bij de verkeerde instantie worden opgevraagd.',
                'names' => [
                    'zaken' => 'Zaken',
                    'documenten' => 'Documenten',
                    'besluiten' => 'Besluiten',
                ],
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
        'save_critical_change' => [
            'modal_heading' => 'Kritieke instelling wijzigen',
            'modal_description' => 'Je wijzigt een cruciale instelling van deze ZGW-koppeling. Daardoor wordt de koppeling automatisch op inactief gezet en moet je de verbinding eerst opnieuw testen en daarna opnieuw activeren. Zolang de ZGW-instantie inactief is, worden zaken van deze gemeente aangemaakt in de gezamenlijke Open Zaak-verbinding. Wil je doorgaan?',
            'confirm' => 'Opslaan en deactiveren',
        ],
    ],

];
