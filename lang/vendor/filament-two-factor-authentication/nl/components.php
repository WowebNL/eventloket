<?php

return [
    'enable' => [
        'header' => 'Je hebt tweestapsverificatie nog niet ingeschakeld.',
        'description' => 'Wanneer tweestapsverificatie is ingeschakeld, wordt tijdens het inloggen gevraagd om een veilige, willekeurige code. Je kunt deze code ophalen via een authenticator-app op je telefoon.',
    ],
    'logout' => [
        'button' => 'Uitloggen',
    ],
    'enabled' => [
        'header' => 'Je hebt tweestapsverificatie ingeschakeld.',
        'description' => 'Mocht je app voor tweestapsverificatie niet meer bruikbaar zijn (bijv omdat je GSM defect of kwijt is geraakt) dan is het mogelijk om met herstelcodes weer toegang te krijgen tot je account. Bewaar deze herstelcodes dus goed in een veilige wachtwoordmanager. Via de knop "Nieuwe herstelcodes genereren" kan je de bestaande herstelcodes opvragen of nieuwe genereren.Een nieuwe QR code opvragen om zo een nieuwe registratie te maken in je authenticator kan mbv de knop "Resetten".',
    ],
    'setup_confirmation' => [
        'header' => 'Rond het inschakelen van tweestapsverificatie af.',
        'description' => 'Wanneer tweestapsverificatie is ingeschakeld, wordt tijdens het inloggen gevraagd om een veilige, willekeurige code. Je kunt deze code ophalen via een authenticator-app op je telefoon.',
        'scan_qr_code' => 'Om het inschakelen van tweestapsverificatie af te ronden, scan je de onderstaande QR-code met een authenticator-app op je telefoon of voer je de instelsleutel in en geef je de gegenereerde OTP-code op.',
    ],
    'base' => [
        'wrong_user' => 'Het geauthenticeerde gebruikersobject moet een Filament Auth-model zijn om het profiel bij te kunnen werken.',
        'rate_limit_exceeded' => 'Te veel verzoeken',
        'try_again' => 'Probeer het opnieuw over :seconds seconden',
    ],
    '2fa' => [
        'confirm' => 'Bevestigen',
        'cancel' => 'Annuleren',
        'enable' => 'Inschakelen',
        'disable' => 'Resetten',
        'confirm_password' => 'Bevestig wachtwoord',
        'wrong_password' => 'Het opgegeven wachtwoord is onjuist.',
        'code' => 'Code',
        'setup_key' => 'Instelsleutel: :setup_key.',
        'current_password' => 'Huidig wachtwoord',
        'regenerate_recovery_codes' => 'Nieuwe herstelcodes genereren',
    ],
    'passkey' => [
        'add' => 'Passkey aanmaken',
        'name' => 'Naam',
        'added' => 'Passkey succesvol toegevoegd.',
        'login' => 'Inloggen met passkey',
    ],
];
