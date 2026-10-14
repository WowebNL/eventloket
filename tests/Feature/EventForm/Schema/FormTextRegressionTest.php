<?php

declare(strict_types=1);

/**
 * Regression tests for form label and text fixes. They guard that:
 *
 *   - the exact prescribed texts are present in the step files;
 *   - old or incorrect texts (typos, default button labels) do not return;
 *   - the Geoman tooltip override in the map picker blade is present.
 *
 * File content assertions are chosen deliberately over reflection: for
 * label strings an exact match is precisely what we want to test, and it
 * runs in milliseconds.
 */
test('#1 Repeater adresVanDeGebouwEn heeft addActionLabel "Nog een adres toevoegen"', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/LocatieVanHetEvenement2Step.php'));
    expect($code)->toContain("Repeater::make('adresVanDeGebouwEn')")
        ->and($code)->toContain("->addActionLabel('Nog een adres toevoegen')");
});

test('#9 Repeater tenten heeft addActionLabel "Wilt u (nog) een tent toevoegen?"', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));
    expect($code)->toContain("Repeater::make('tenten')")
        ->and($code)->toContain("->addActionLabel('Wilt u (nog) een tent toevoegen?')");
});

test('#10 Repeater podia heeft addActionLabel "Wilt u (nog) een podium toevoegen?"', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));
    expect($code)->toContain("Repeater::make('podia')")
        ->and($code)->toContain("->addActionLabel('Wilt u (nog) een podium toevoegen?')");
});

test('Repeater overkappingen heeft addActionLabel "Wilt u (nog) een overkapping toevoegen?"', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));
    expect($code)->toContain("Repeater::make('overkappingen')")
        ->and($code)->toContain("->addActionLabel('Wilt u (nog) een overkapping toevoegen?')");
});

test('bouwsels-repeaters gebruiken geen inconsistente oude knoplabels meer', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));
    // The previous labels were inconsistent with each other and must not return.
    expect($code)->not->toContain("->addActionLabel('Wilt u nog een tent toevoegen?')")
        ->and($code)->not->toContain("->addActionLabel('Wilt u podium toevoegen?')")
        ->and($code)->not->toContain("->addActionLabel('Nog een overkapping toevoegen')");
});

test('#12 drank-vraag is positief geformuleerd (geen dubbele ontkenning)', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));
    // Nieuwe positieve formulering.
    expect($code)->toContain('Is het aantal aaneengesloten dagen voor het verstrekken van drank minder dan 12?');
    // Oude negatieve ontkenning mag niet terugkomen.
    expect($code)->not->toContain('is niet meer dan 12 aaneengesloten dagen?');
});

test('#16 typo "aanraag" niet langer in content200; "aanvraag" wel', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/LocatieVanHetEvenement2Step.php'));
    // Geen typo meer.
    expect($code)->not->toContain('aanraag');
    // Wel de juiste tekst — bewaakt ook dat de regel zelf niet per ongeluk verwijderd is.
    expect($code)->toContain('U gaat verder met deze aanvraag voor de gemeente');
});

test('kalender-link in TijdenStep gebruikt de route i.p.v. een hardcoded omgevings-URL', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/TijdenStep.php'));
    // Geen omgevings-specifieke URL meer in de broncode.
    expect($code)->not->toContain('woweb.app');
    // De link wordt opgebouwd via de Calendar-page-route van het organiser-panel.
    expect($code)->toContain("Calendar::getUrl(panel: 'organiser'");
});

test('#3 Geoman gummetje-tooltip "Bewaar" wordt overschreven naar "Klaar"', function () {
    $blade = file_get_contents(resource_path('views/vendor/map-picker/fields/osm-map-picker.blade.php'));
    // Custom language is geregistreerd...
    expect($blade)->toContain("L.PM.setLang('nlEventloket'");
    // ...met override van de finish-actie naar 'Klaar'.
    expect($blade)->toContain("finish: 'Klaar'");
    // En de map gebruikt die language ipv kale 'nl'.
    expect($blade)->toContain("activeLang = 'nlEventloket'");
});
