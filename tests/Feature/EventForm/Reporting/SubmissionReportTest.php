<?php

declare(strict_types=1);

/**
 * SubmissionReport bouwt het inzendingsbewijs op basis van de
 * Filament-step-definities + de FormState. De opdrachtgever
 * rapporteerde dat de oude PDF maar 18 velden toonde, terwijl
 * organisators tientallen vragen kunnen beantwoorden — alle data
 * moet in het inzendingsbewijs terechtkomen.
 *
 * Onze fix: één service die elke stap afloopt, alle ingevulde velden
 * met hun (template-rendered) label terugstuurt, en stappen zonder
 * inhoud overslaat. Deze test bewijst dat:
 *
 *   1. Een lege state geen secties oplevert.
 *   2. Een state met enkele velden secties levert die alleen die
 *      ingevulde velden bevatten.
 *   3. DateTimePickers worden human-readable geformat.
 *   4. Radio-velden tonen de optie-label, niet de interne waarde.
 */

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\BijlagenStep;
use App\EventForm\Schema\Steps\ContactgegevensStep;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\Schema\Steps\TypeAanvraagStep;
use App\EventForm\State\FormState;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Wizard\Step;

test('lege state → geen secties (alle stappen worden overgeslagen)', function () {
    $sections = app(SubmissionReport::class)->build(
        FormState::empty(),
        EventFormSchema::steps(),
    );

    expect($sections)->toBe([]);
});

test('alleen contact-velden ingevuld → één sectie Contactgegevens', function () {
    $state = new FormState(values: [
        'watIsUwVoornaam' => 'Eva',
        'watIsUwAchternaam' => 'de Vries',
        'watIsUwEMailadres' => 'eva@example.nl',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [ContactgegevensStep::make()]);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Contactgegevens');

    // Drie ingevulde velden moeten als entries terugkomen; we toetsen
    // op aanwezigheid van de waarden i.p.v. exacte labels — de labels
    // zijn template-rendered en kunnen veranderen zonder dat de
    // grondslag (alle data in de PDF) breekt.
    $waarden = collect($sections[0]['entries'])->pluck('value')->all();
    expect($waarden)->toContain('Eva')
        ->and($waarden)->toContain('de Vries')
        ->and($waarden)->toContain('eva@example.nl');
});

test('Tijden-stap: DateTimePicker-waarden landen in de overzichts-tabel met human-readable datums', function () {
    // Behandelaars gebruiken de PDF veel — voor Tijden willen we
    // dezelfde tabel als op het formulier: Activiteit / Start / Eind.
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:30',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TijdenStep::make()]);

    $tabelEntry = collect($sections[0]['entries'])->first(fn ($e) => ! empty($e['table']));
    expect($tabelEntry)->not->toBeNull()
        ->and($tabelEntry['table']['header'])->toBe(['Activiteit', 'Start', 'Eind']);

    // Eén rij (Publiek) want opbouw/afbouw zijn niet ingevuld.
    expect($tabelEntry['table']['rows'])->toHaveCount(1)
        ->and($tabelEntry['table']['rows'][0])->toBe([
            'Publiek',
            '14 juni 2026 · 14:30',
            '14 juni 2026 · 18:00',
        ]);

    // Diezelfde EvenementStart/Eind mogen niet óók nog als losse rij in
    // de PDF verschijnen — anders staat alles dubbel.
    $waarden = collect($sections[0]['entries'])->pluck('value')->filter()->all();
    expect($waarden)->not->toContain('14 juni 2026 · 14:30')
        ->and($waarden)->not->toContain('14 juni 2026 · 18:00');
});

test('Tijden-stap: alle drie de blokken ingevuld → tabel met 3 rijen in volgorde Opbouw/Publiek/Afbouw', function () {
    $state = new FormState(values: [
        'OpbouwStart' => '2026-06-14T08:00',
        'OpbouwEind' => '2026-06-14T13:30',
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T22:00',
        'AfbouwStart' => '2026-06-14T22:00',
        'AfbouwEind' => '2026-06-15T01:00',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TijdenStep::make()]);
    $tabelEntry = collect($sections[0]['entries'])->first(fn ($e) => ! empty($e['table']));

    expect($tabelEntry['table']['rows'])->toBe([
        ['Opbouw', '14 juni 2026 · 08:00', '14 juni 2026 · 13:30'],
        ['Publiek', '14 juni 2026 · 14:00', '14 juni 2026 · 22:00'],
        ['Afbouw', '14 juni 2026 · 22:00', '15 juni 2026 · 01:00'],
    ]);
});

test('Tijden-stap: geen enkele datetime ingevuld → geen tabel-entry', function () {
    // Andere velden in TijdenStep (de Radio-vragen) kunnen wel ingevuld
    // zijn; de tabel zelf moet bij ontbrekende datums niet als een
    // lege strook verschijnen.
    $state = new FormState(values: [
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TijdenStep::make()]);

    if ($sections === []) {
        expect($sections)->toBe([]);
    } else {
        $tabelEntry = collect($sections[0]['entries'])->first(fn ($e) => ! empty($e['table']));
        expect($tabelEntry)->toBeNull();
    }
});

test('Type-aanvraag-stap: vergunning + ontheffingen → entry met afgeleide onderdelen', function () {
    // Behandelaars willen op de samenvatting + in de PDF zien wélke
    // aanvraag-soorten in het spel zijn. TypeAanvraagStep heeft geen
    // Field-componenten — SubmissionReport leidt het zelf af uit
    // FormState (zelfde logica als het content35-template op de stap).
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        // `alcoholvergunning` wordt door FormDerivedState afgeleid uit
        // `kruisAanWatVanToepassingIsVoorUwEvenementX.A5` en heeft de
        // waarde `'Ja'` (string), niet `true`.
        'alcoholvergunning' => 'Ja',
        'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A3' => true, 'A4' => true, 'A5' => true],
        'kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX' => ['A48' => true, 'A51' => true],
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TypeAanvraagStep::make()]);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Aanvraag')
        ->and($sections[0]['entries'])->toHaveCount(2);

    // Entry 0: het aanvraag-type zelf (afgeleid uit FormState).
    expect($sections[0]['entries'][0]['value'])->toBe('Evenementenvergunning');

    // Entry 1: onderdelen die de aanvrager zelf moet regelen.
    $zelfTeRegelen = $sections[0]['entries'][1]['value'];
    foreach ([
        'Ontheffing Alcoholwet',
        'Gebruiksmelding brandveilig gebruik en basishulpverlening overige plaatsen',
        'Ontheffing plaatsen object of parkeren grote voertuigen op de openbare weg',
        'Vergunning kansspelen',
    ] as $verwacht) {
        expect($zelfTeRegelen)->toContain($verwacht);
    }
    // Aanstellingsbesluit is tijdelijk uitgeschakeld (commented out).
    expect($zelfTeRegelen)->not->toContain('Aanstellingsbesluit verkeersregelaars');
});

test('Type-aanvraag-stap: vooraankondiging-pad → alleen Vooraankondiging', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TypeAanvraagStep::make()]);

    expect($sections[0]['entries'][0]['value'])->toBe('Vooraankondiging');
});

test('Type-aanvraag-stap: meldingspad → Melding zonder aanvullende ontheffingen', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    $sections = app(SubmissionReport::class)->build($state, [TypeAanvraagStep::make()]);

    expect($sections[0]['entries'][0]['value'])->toBe('Melding');
});

test('Bijlagen-stap: FileUpload-velden krijgen een `files`-array per bestandsvraag', function () {
    // Behandelaars willen in de PDF + samenvatting per bestandsvraag een
    // <ul> met alle geuploade bestand-namen — niet één lange comma-rij.
    // SubmissionReport bouwt daarvoor een aparte `files`-array op.
    $state = new FormState(values: [
        'veiligheidsplan' => 'event-form-uploads/veiligheidsplan-buurtfeest.pdf',
        'bijlagen1' => [
            'event-form-uploads/draaiboek.pdf',
            'event-form-uploads/situatietekening.pdf',
        ],
    ]);

    $sections = app(SubmissionReport::class)->build($state, [BijlagenStep::make()]);

    expect($sections)->toHaveCount(1);
    $entries = $sections[0]['entries'];

    $veiligheidsplan = collect($entries)->first(fn ($e) => str_contains(strtolower($e['label'] ?? ''), 'veiligheidsplan'));
    expect($veiligheidsplan)->not->toBeNull()
        ->and($veiligheidsplan['files'] ?? [])->toBe(['veiligheidsplan-buurtfeest.pdf']);

    $bijlagen = collect($entries)->first(fn ($e) => str_contains(strtolower($e['label'] ?? ''), 'overige bijlagen'));
    expect($bijlagen)->not->toBeNull()
        ->and($bijlagen['files'] ?? [])->toBe(['draaiboek.pdf', 'situatietekening.pdf']);
});

test('Type-aanvraag-stap: lege state → geen entry, geen sectie', function () {
    // Niemand heeft iets ingevuld → niets te zeggen over het type
    // aanvraag. Geen lege sectie tonen.
    $sections = app(SubmissionReport::class)->build(FormState::empty(), [TypeAanvraagStep::make()]);

    expect($sections)->toBe([]);
});

test('Repeater-rijen worden uitgeklapt naar sub-entries in plaats van samengevat', function () {
    // Eén ingevulde adres-rij in adresVanDeGebouwEn moet niet als
    // "1 rij(en) ingevuld" verschijnen, maar als label-rij met de
    // sub-velden eronder (postcode, huisnummer, etc.).
    $state = new FormState(values: [
        'adresVanDeGebouwEn' => [
            [
                'naamVanDeLocatieGebouw' => 'Buurtcentrum De Hoek',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411CD',
                    'huisnummer' => '1',
                ],
            ],
        ],
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [LocatieVanHetEvenement2Step::make()],
    );

    expect($sections)->toHaveCount(1);
    $entries = $sections[0]['entries'];

    // Eerste entry is een Repeater-rij met sub-entries
    $repeaterRow = collect($entries)->first(fn ($e) => ! empty($e['sub']));
    expect($repeaterRow)->not->toBeNull();
    expect($repeaterRow['sub'])->toBeArray();

    $subValues = collect($repeaterRow['sub'])->pluck('value')->all();
    expect($subValues)->toContain('Buurtcentrum De Hoek')
        ->and($subValues)->toContain('6411CD');
});

test('Map-state met geojson levert een SVG mee in de entry', function () {
    // locatieSOpKaart is een Repeater met `buitenLocatieVanHetEvenement`
    // als Map-state per rij. SubmissionReport moet de polygon-geojson
    // herkennen en een SVG meeleveren in de sub-entry.
    $state = new FormState(values: [
        'locatieSOpKaart' => [
            [
                'naamVanDeLocatieKaart' => 'Plein',
                'buitenLocatieVanHetEvenement' => [
                    'lat' => 50.85,
                    'lng' => 5.69,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'geometry' => [
                                    'type' => 'Polygon',
                                    'coordinates' => [[
                                        [5.69, 50.85], [5.70, 50.85],
                                        [5.70, 50.86], [5.69, 50.86], [5.69, 50.85],
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [LocatieVanHetEvenement2Step::make()],
    );

    // Verzamel alle sub-entries (uit Repeater-rijen) + top-level entries.
    $allEntries = [];
    foreach ($sections as $section) {
        foreach ($section['entries'] as $entry) {
            $allEntries[] = $entry;
            foreach ($entry['sub'] ?? [] as $sub) {
                $allEntries[] = $sub;
            }
        }
    }
    $mapEntry = collect($allEntries)->first(fn ($e) => ! empty($e['svg']));

    expect($mapEntry)->not->toBeNull()
        ->and($mapEntry['svg'])->toContain('<img')
        ->and($mapEntry['svg'])->toContain('data:image/svg+xml;base64,');

    // Decoderen: de polygon-vorm moet nog steeds binnen de SVG zitten.
    preg_match('/base64,([^"]+)/', $mapEntry['svg'], $m);
    $rawSvg = base64_decode($m[1]);
    expect($rawSvg)->toContain('<svg')
        ->and($rawSvg)->toContain('<path'); // polygon-vorm
});

test('stappen zonder ingevulde velden worden weggelaten uit het overzicht', function () {
    // Tijden ingevuld, contact niet → alleen één sectie verwacht.
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:30',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $sections = app(SubmissionReport::class)->build(
        $state,
        [ContactgegevensStep::make(), TijdenStep::make()],
    );

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Tijden');
});

test('Radio met Closure-options resolveert label uit dynamische bron (#2 Michel: gemeentenaam ipv brk-code)', function () {
    // userSelectGemeente heeft dynamic options uit `inGemeentenResponse.all.items` (brk → name).
    // Vóór de fix viel renderSelectValue terug op de raw value en toonde
    // de samenvatting 'GM1954' i.p.v. 'Beekdaelen'. De stub-livewire
    // evalueert de Closure nu netjes.
    $state = new FormState(values: [
        'userSelectGemeente' => 'GM1954',
        'inGemeentenResponse' => ['all' => ['items' => [
            ['brk_identification' => 'GM1954', 'name' => 'Beekdaelen'],
            ['brk_identification' => 'GM0888', 'name' => 'Heerlen'],
        ]]],
    ]);

    $step = Step::make('Test')->schema([
        Radio::make('userSelectGemeente')
            ->options(fn ($livewire): array => collect((array) $livewire->state()->get('inGemeentenResponse.all.items'))
                ->mapWithKeys(fn ($item) => [(string) ($item['brk_identification'] ?? '') => (string) ($item['name'] ?? '')])
                ->all()),
    ]);

    $sections = app(SubmissionReport::class)->build($state, [$step]);

    expect($sections)->toHaveCount(1);
    $waarden = collect($sections[0]['entries'])->pluck('value')->all();
    expect($waarden)->toContain('Beekdaelen')
        ->and($waarden)->not->toContain('GM1954');
});
