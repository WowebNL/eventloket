<?php

declare(strict_types=1);

/**
 * Working out the submission deadline was covered, showing it was not, and
 * that is where the problem sat: on the date step someone filing a report
 * saw three risk classifications that are not about them, and on their own
 * step no deadline at all. These tests read the text blocks back out of the
 * steps and check what actually ends up in them.
 */

use App\EventForm\Schema\Steps\MeldingStep;
use App\EventForm\Schema\Steps\RisicoscanStep;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\State\FormState;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;

/**
 * Render the text block with the given name out of a wizard step. The
 * `state()` closure is normally handed the Livewire page; a stub returning
 * the FormState is enough here, because that is the only thing these blocks
 * take from it.
 */
function termijnBlok(Step $step, string $name, FormState $state): string
{
    $entry = null;
    $walk = function (object $component) use (&$walk, &$entry, $name): void {
        if ($component instanceof TextEntry && $component->getName() === $name) {
            $entry = $component;
        }

        if (! property_exists($component, 'childComponents')) {
            return;
        }
        $childProp = (new ReflectionObject($component))->getProperty('childComponents');
        $childProp->setAccessible(true);
        foreach ($childProp->getValue($component) as $componentList) {
            if (! is_array($componentList)) {
                continue;
            }
            foreach ($componentList as $child) {
                if (is_object($child)) {
                    $walk($child);
                }
            }
        }
    };
    $walk($step);

    expect($entry)->not->toBeNull("tekstblok {$name} ontbreekt in de stap");

    $stub = new class($state)
    {
        public function __construct(private readonly FormState $state) {}

        public function state(): FormState
        {
            return $this->state;
        }
    };

    $prop = (new ReflectionObject($entry))->getProperty('getConstantStateUsing');
    $prop->setAccessible(true);

    return (string) ($prop->getValue($entry))($stub);
}

function termijnRenderState(array $values = [], array $gemeenteVariabelen = []): FormState
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

describe('Tijden-stap', function () {
    test('een melder ziet zijn eigen termijn en geen A/B/C-opsomming', function () {
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState([
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        ]));

        expect($html)->toContain('minimaal <strong>4 weken</strong> voor een melding')
            ->and($html)->not->toContain('A-evenement')
            ->and($html)->not->toContain('B-evenement')
            ->and($html)->not->toContain('C-evenement');
    });

    test('een vergunningaanvrager ziet alleen de klassetermijnen', function () {
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState([
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        ]));

        expect($html)->toContain('A-evenement (klein)')
            ->and($html)->toContain('C-evenement (groot)')
            ->and($html)->not->toContain('voor een melding');
    });

    test('zolang de scan loopt staan alle ingestelde termijnen er', function () {
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState());

        expect($html)->toContain('A-evenement (klein)')
            ->and($html)->toContain('voor een melding');
    });

    test('een melder zonder ingestelde termijn krijgt geen klassen te zien', function () {
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState(
            ['wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee'],
            ['indieningstermijn_melding' => 0],
        ));

        expect($html)->toContain('indieningstermijn voor een melding')
            ->and($html)->not->toContain('A-evenement');
    });
});

describe('Vooraankondiging', function () {
    test('een vooraankondiging ziet alleen de klassetermijnen, net als voorheen', function () {
        // A vooraankondiging is out of scope for this feature, but it does
        // walk the date step, so without an exception it would pick up an
        // extra report line that does not concern it.
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState([
            'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        ]));

        expect($html)->toContain('A-evenement (klein)')
            ->and($html)->toContain('B-evenement (middelgroot)')
            ->and($html)->toContain('C-evenement (groot)')
            ->and($html)->not->toContain('voor een melding');
    });

    test('een vooraankondiging met alle scanvragen Ja blijft buiten het meldingpad', function () {
        $html = termijnBlok(TijdenStep::make(), 'content2', termijnRenderState(
            [
                'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
                'reportQuestion_1' => 'Ja',
            ],
            [
                'use_new_report_questions' => true,
                'report_questions' => [['id' => 1, 'order' => 1, 'question' => 'Vraag 1']],
            ],
        ));

        expect($html)->toContain('A-evenement (klein)')
            ->and($html)->not->toContain('voor een melding');
    });
});

describe('Melding-stap', function () {
    test('een melding binnen de termijn krijgt een bevestiging in melding-taal', function () {
        Carbon::setTestNow('2026-06-01');

        $html = termijnBlok(MeldingStep::make(), 'meldingIndieningstermijnContent', termijnRenderState([
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        ]));

        expect($html)->toContain('eventform-alert-success')
            ->and($html)->toContain('Uw melding valt binnen de indieningstermijn van <strong>4 weken</strong>')
            ->and($html)->not->toContain('risicoclassificatie');
    });

    test('een melding buiten de termijn krijgt een waarschuwing in melding-taal', function () {
        Carbon::setTestNow('2026-08-20');

        $html = termijnBlok(MeldingStep::make(), 'meldingIndieningstermijnContent', termijnRenderState([
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        ]));

        expect($html)->toContain('eventform-alert-warning')
            ->and($html)->toContain('de indieningstermijn voor een melding is <strong>4 weken</strong>')
            ->and($html)->toContain('U kunt de melding nog steeds indienen')
            ->and($html)->not->toContain('risicoclassificatie');
    });

    test('het nieuwe vragensysteem levert hetzelfde blok op', function () {
        Carbon::setTestNow('2026-06-01');

        $html = termijnBlok(MeldingStep::make(), 'meldingIndieningstermijnContent', termijnRenderState(
            ['reportQuestion_1' => 'Ja'],
            [
                'use_new_report_questions' => true,
                'report_questions' => [['id' => 1, 'order' => 1, 'question' => 'Vraag 1']],
            ],
        ));

        expect($html)->toContain('Uw melding valt binnen de indieningstermijn van <strong>4 weken</strong>');
    });

    test('zonder ingestelde termijn blijft het blok leeg', function () {
        Carbon::setTestNow('2026-06-01');

        $html = termijnBlok(MeldingStep::make(), 'meldingIndieningstermijnContent', termijnRenderState(
            ['wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee'],
            ['indieningstermijn_melding' => 0],
        ));

        expect($html)->toBe('');
    });
});

describe('Risicoscan-stap', function () {
    test('een vergunningaanvraag houdt de bestaande klassetekst', function () {
        Carbon::setTestNow('2026-06-01');

        $state = termijnRenderState([
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
        ]);

        expect($state->get('risicoClassificatie'))->toBe('A');

        $classificatieBlok = termijnBlok(RisicoscanStep::make(), 'risicoClassificatieContent', $state);
        $termijnBlok = termijnBlok(RisicoscanStep::make(), 'indieningstermijnContent', $state);

        expect($classificatieBlok)->toContain('De indieningstermijn voor een A-evenement bij de gemeente Testgemeente is <strong>8 weken</strong>')
            ->and($termijnBlok)->toContain('Uw aanvraag valt binnen de indieningstermijn van <strong>8 weken</strong>');
    });

    test('de intro somt de klassetermijnen op, niet de melding-termijn', function () {
        $html = termijnBlok(RisicoscanStep::make(), 'content', termijnRenderState());

        expect($html)->toContain('A (klein): <strong>8 weken</strong>')
            ->and($html)->toContain('B (middelgroot): <strong>13 weken</strong>')
            ->and($html)->toContain('C (groot): <strong>23 weken</strong>')
            ->and($html)->not->toContain('melding');
    });
});
