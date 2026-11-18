<?php

declare(strict_types=1);

/**
 * The summary and the PDF are the two places where the submission deadline
 * leaves the form: the organiser reads it back and the case handler
 * receives it. Until now both spoke exclusively about an application with a
 * risk classification. These tests check what ends up there for a report,
 * and that the permit path is unchanged.
 */

use App\EventForm\Schema\CustomSteps\SamenvattingStep;
use App\EventForm\State\FormState;
use App\Models\Zaak;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;

function termijnSamenvattingHtml(FormState $state): string
{
    $ref = new ReflectionObject($step = SamenvattingStep::make());
    $prop = $ref->getProperty('childComponents');
    $prop->setAccessible(true);
    $children = $prop->getValue($step);

    $entry = collect($children['default'] ?? [])->first(
        fn ($component): bool => $component instanceof TextEntry && $component->getName() === 'samenvattingOverzicht',
    );

    expect($entry)->not->toBeNull('samenvattingOverzicht ontbreekt');

    $stub = new class($state)
    {
        public function __construct(private readonly FormState $state) {}

        public function stateAsAsked(): FormState
        {
            return $this->state;
        }
    };

    $stateProp = (new ReflectionObject($entry))->getProperty('getConstantStateUsing');
    $stateProp->setAccessible(true);

    return (string) ($stateProp->getValue($entry))($stub);
}

function samenvattingState(array $values = [], array $gemeenteVariabelen = []): FormState
{
    return new FormState(values: array_merge([
        'evenementInGemeente' => ['name' => 'Testgemeente'],
        'gemeenteVariabelen' => array_merge([
            'indieningstermijn_a' => 8,
            'indieningstermijn_b' => 13,
            'indieningstermijn_c' => 23,
            'indieningstermijn_melding' => 4,
        ], $gemeenteVariabelen),
        'EvenementStart' => '2026-09-01T10:00:00+02:00',
    ], $values));
}

test('de samenvatting toont de melding-termijn en geen risicoklassen', function () {
    Carbon::setTestNow('2026-06-01');

    $html = termijnSamenvattingHtml(samenvattingState([
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]));

    expect($html)->toContain('Uw melding valt binnen de indieningstermijn van <strong>4 weken</strong>')
        ->and($html)->toContain('Melding: <strong>4 weken</strong>')
        ->and($html)->not->toContain('Risicoclassificatie:')
        ->and($html)->not->toContain('A: <strong>8 weken</strong>');
});

test('de samenvatting van een vergunningaanvraag blijft ongewijzigd', function () {
    Carbon::setTestNow('2026-06-01');

    $html = termijnSamenvattingHtml(samenvattingState([
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.25',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '0.5',
        'isErSprakeVanOvernachten' => '0',
        'isErGebruikVanAlcoholEnDrugs' => '0',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '0',
        'inWelkSeizoenVindtHetEvenementPlaats' => '0.25',
        'inWelkeLocatieVindtHetEvenementPlaats' => '0.25',
        'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0.25',
        'watIsDeTijdsduurVanHetEvenement' => '0',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0',
    ]));

    expect($html)->toContain('Risicoclassificatie:')
        ->and($html)->toContain('Uw aanvraag valt binnen de indieningstermijn van <strong>8 weken</strong>')
        ->and($html)->toContain('A: <strong>8 weken</strong>')
        ->and($html)->not->toContain('Melding: <strong>');
});

test('de PDF toont de termijn van een melding zonder risicoklasse', function () {
    Carbon::setTestNow('2026-06-01');

    $state = samenvattingState([
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    $html = view('pdf.submission-report', [
        'zaak' => Zaak::factory()->make(['public_id' => 'ZAAK-00001']),
        'vervangtVooraankondiging' => null,
        'organisatorNaam' => null,
        'state' => $state,
        'sections' => [],
        'gemeenteNaam' => 'Testgemeente',
        'risicoClassificatie' => $state->get('risicoClassificatie'),
        'indieningstermijnStatus' => $state->get('indieningstermijnStatus'),
        'naamEvenement' => 'Testevenement',
        'akkoordGegeven' => true,
    ])->render();

    expect($html)->toContain('<strong>Indieningstermijn:</strong>')
        ->and($html)->toContain('Binnen termijn (4 weken)')
        ->and($html)->not->toContain('Risicoclassificatie:');
});

test('de samenvatting van een vooraankondiging toont geen melding-termijn', function () {
    Carbon::setTestNow('2026-06-01');

    $html = termijnSamenvattingHtml(samenvattingState([
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]));

    expect($html)->toContain('A: <strong>8 weken</strong>')
        ->and($html)->toContain('C: <strong>23 weken</strong>')
        ->and($html)->not->toContain('Melding: <strong>');
});
