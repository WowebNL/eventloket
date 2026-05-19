<?php

declare(strict_types=1);

/**
 * SamenvattingStep is een hand-geschreven wizard-stap die vóór de
 * Type-aanvraag-stap komt. Twee taken:
 *
 *  1. Toon alle ingevulde waarden gegroepeerd per OF-stap (zelfde
 *     opmaak als de submission-PDF).
 *  2. Verplicht een AVG-akkoord-checkbox voordat de organisator kan
 *     indienen — privacy-compliance vereist explicit consent.
 */

use App\EventForm\Schema\CustomSteps\SamenvattingStep;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;

function samenvattingChildren(Step $step): array
{
    $ref = new ReflectionObject($step);
    $prop = $ref->getProperty('childComponents');
    $prop->setAccessible(true);
    $children = $prop->getValue($step);

    return is_array($children) && is_array($children['default'] ?? null) ? $children['default'] : [];
}

test('Type-aanvraag komt vlak vóór de Samenvatting in de wizard', function () {
    // Behandelaars / organisators willen op de Samenvatting eerst zien
    // wélke aanvraag er gedaan wordt; Type-aanvraag staat daarom direct
    // ervóór en is óók opgenomen in de Samenvatting + PDF zelf.
    $steps = EventFormSchema::steps();
    $labels = collect($steps)->map(fn (Step $s) => $s->getLabel())->all();
    $typeAanvraagIndex = array_search('Aanvraag', $labels, true);
    $samenvattingIndex = array_search('Samenvatting', $labels, true);

    expect($typeAanvraagIndex)->toBeInt('Aanvraag-stap ontbreekt')
        ->and($samenvattingIndex)->toBeInt('Samenvatting-stap ontbreekt')
        ->and($samenvattingIndex - $typeAanvraagIndex)->toBe(1);
});

test('Samenvatting bevat een verplichte akkoord-checkbox', function () {
    $children = samenvattingChildren(SamenvattingStep::make());

    $checkbox = collect($children)->first(
        fn ($c) => $c instanceof Checkbox && $c->getName() === 'akkoordVerwerkingGegevens'
    );

    expect($checkbox)->not->toBeNull('akkoordVerwerkingGegevens-checkbox ontbreekt');

    // Required + accepted = uitsluitend `true` is een geldige waarde,
    // anders blokkeert validation de submit.
    $reflection = new ReflectionObject($checkbox);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);
    $rules = $rulesProp->getValue($checkbox);

    $heeftAcceptedRule = collect($rules)->contains(function ($entry): bool {
        [$rule] = $entry;

        return is_string($rule) && $rule === 'accepted';
    });

    expect($heeftAcceptedRule)->toBeTrue('checkbox heeft geen accepted-rule, waardoor leeg laten zou doorglippen')
        ->and($checkbox->isRequired())->toBeTrue();
});

function samenvattingHtml(FormState $state): string
{
    $children = samenvattingChildren(SamenvattingStep::make());
    /** @var TextEntry $overzicht */
    $overzicht = collect($children)->first(fn ($c) => $c instanceof TextEntry && $c->getName() === 'samenvattingOverzicht');

    expect($overzicht)->not->toBeNull('samenvattingOverzicht-TextEntry ontbreekt');

    $reflection = new ReflectionObject($overzicht);
    $prop = $reflection->getProperty('getConstantStateUsing');
    $prop->setAccessible(true);
    $closure = $prop->getValue($overzicht);

    $stub = new class($state)
    {
        public function __construct(private readonly FormState $state) {}

        public function state(): FormState
        {
            return $this->state;
        }
    };

    return (string) $closure($stub);
}

test('Samenvatting toont een titel "Samenvatting" bovenaan', function () {
    // Direct boven de inhouds-tabellen wil de organisator zien wat hij
    // bekijkt. Voorheen ontbrak de kop volledig.
    $html = samenvattingHtml(new FormState(values: [
        'watIsUwVoornaam' => 'Eva',
    ]));

    expect($html)->toContain('<h2')
        ->and($html)->toContain('Samenvatting');
});

test('Samenvatting rendert kaart-SVG voor Map-state met geojson, niet de raw geojson-tekst', function () {
    // De PDF rendert al een kaartje voor ingetekende polygonen (zie
    // `SubmissionReport::renderGeoJsonSvg()`). De Samenvatting deed dat
    // nog niet en toonde daardoor een onleesbare "Polygon (5 punten)"-
    // tekst. Met de nieuwe blade-partial pakt 'ie dezelfde svg op.
    // Na `LocatiePolygonsPatch` is `locatieSOpKaart` een directe Map-state
    // (niet meer als Repeater-rij geneste). Shape = { lat, lng, geojson: {...} }.
    $html = samenvattingHtml(new FormState(values: [
        'naamVanDeLocatieKaart' => 'Plein',
        'locatieSOpKaart' => [
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
    ]));

    expect($html)->toContain('<img')
        ->and($html)->toContain('data:image/svg+xml')
        ->and($html)->not->toContain('Polygon (5 punten)');
});

test('Samenvatting rendert tijden-tabel ipv losse rijen voor de TijdenStep', function () {
    // De PDF gebruikt een 3×Activiteit/Start/Eind-tabel; de Samenvatting
    // moet dezelfde tabel weergeven zodat de organisator één-op-één
    // herkent wat 'ie indient.
    $html = samenvattingHtml(new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
    ]));

    expect($html)->toContain('Activiteit')
        ->and($html)->toContain('Publiek')
        ->and($html)->toContain('14 juni 2026 · 14:00');
});

test('Samenvatting toont leeg-melding wanneer geen velden zijn ingevuld', function () {
    $html = samenvattingHtml(FormState::empty());

    expect($html)->toContain('U heeft nog geen velden ingevuld');
});
