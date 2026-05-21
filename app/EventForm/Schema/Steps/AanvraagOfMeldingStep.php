<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use App\EventForm\State\FormState;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid d87c01ce-8387-43b0-a8c8-e6cf5abb6da1
 *
 * @openforms-step-index 5
 */
final class AanvraagOfMeldingStep
{
    public const UUID = 'd87c01ce-8387-43b0-a8c8-e6cf5abb6da1';

    public static function make(): Step
    {
        return Step::make('Vergunningsplichtig scan')
            ->key(self::UUID)
            ->schema([
                InfoText::info('content4', '<p>Wij stellen u enkele vragen over uw evenement om te beoordelen of uw evenement meldingsplichtig of vergunningsplichtig is.</p> <p>Uw evenement vindt plaats binnen de gemeente: <strong>{% get_value evenementInGemeente \'name\' %}</strong></p>')
                    ->hidden(Hidden::rule('contentGemeenteMelding')),
                Fieldset::make('Algemene vragen')
                    ->columns(1)
                    ->schema([
                        // === NIEUW PAD: ReportQuestion-systeem ===
                        //
                        // 10 vaste slots `reportQuestion_1..10` in een Group
                        // wrapper. De Group is hidden wanneer de gemeente
                        // nog niet naar het nieuwe systeem is overgeschakeld
                        // — zo nemen die slots geen ruimte in op de pagina
                        // voor gemeenten die nog op het oude systeem zitten.
                        Group::make(self::reportQuestionRadios())
                            ->hidden(fn ($livewire): bool => $livewire->state()->get('gemeenteVariabelen.use_new_report_questions') !== true),

                        // === LEGACY PAD: hardcoded vragen ===
                        // Hele blok verbergen wanneer de gemeente naar het
                        // nieuwe ReportQuestion-systeem is overgeschakeld;
                        // de bestaande per-veld hidden-cascade blijft
                        // ongewijzigd voor het oude pad.
                        Group::make([
                            Radio::make('isHetAantalAanwezigenBijUwEvenementMinderDanSdf')
                                ->label(Label::render('Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen \'aanwezigen\' %} personen?'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen')
                                ->label(Label::render('Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('WordtErAlleenMuziekGeluidGeproduceerdTussen')
                                ->label(Label::render('Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('WordtErAlleenMuziekGeluidGeproduceerdTussen');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('IsdeGeluidsproductieLagerDan')
                                ->label(Label::render('Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('IsdeGeluidsproductieLagerDan');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten')
                                ->label('Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?')
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('IsdeGeluidsproductieLagerDan') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst')
                                ->label(Label::render('Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('indienErObjectenGeplaatstWordenZijnDezeDanKleiner')
                                ->label(Label::render('Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? '))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(function (Get $get, $livewire): bool {
                                    $rule = $livewire->state()->isFieldHidden('indienErObjectenGeplaatstWordenZijnDezeDanKleiner');
                                    if ($rule !== null) {
                                        return $rule;
                                    }

                                    return ! ($get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Ja');
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag1')
                                ->label(Label::render('{{ gemeenteVariabelen.report_question_1 }}'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('meldingvraag1'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag2')
                                ->label(Label::render('{{ gemeenteVariabelen.report_question_2 }}'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('meldingvraag2'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag3')
                                ->label(Label::render('{{ gemeenteVariabelen.report_question_3 }}'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('meldingvraag3'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag4')
                                ->label(Label::render('{{ gemeenteVariabelen.report_question_4 }}'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('meldingvraag4'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag5')
                                ->label(Label::render('{{ gemeenteVariabelen.report_question_5 }}'))
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('meldingvraag5'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer')
                                ->label('Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?')
                                ->options(JaNeeOptions::OPTIONS)
                                ->required()
                                ->hidden(Hidden::rule('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                        ])
                            ->hidden(fn ($livewire): bool => ! self::legacySysteemActief($livewire->state())),

                        // Uitkomst-teksten staan BUITEN beide systeem-Groups
                        // zodat ze in beide paden zichtbaar kunnen worden.
                        // De hidden-Closure switcht tussen het legacy
                        // FormFieldVisibility-pad en de afgeleide
                        // `isVergunningaanvraag`-flag uit FormDerivedState.
                        InfoText::info('contentGoNext', '<p>Voor uw evenement is een vergunning noodzakelijk. U wordt in Evenloket doorgeleid naar de vragen voor het aanvragen van een vergunning voor uw evenement.</p>')
                            ->hidden(fn ($livewire): bool => self::contentGoNextHidden($livewire->state())),
                        InfoText::info('MeldingTekst', '<p>Voor uw evenement is geen vergunning noodzakelijk, maar is een melding voldoende. U wordt in Eventloket doorgeleid naar de vragen voor het indienen van een melding.</p>')
                            ->hidden(fn ($livewire): bool => self::meldingTekstHidden($livewire->state())),
                    ])
                    ->hidden(Hidden::rule('algemeneVragen')),
            ]);
    }

    /**
     * Bouw 10 vaste Radio-slots `reportQuestion_1..10` op die hun label
     * uit `gemeenteVariabelen.report_questions[index]` lezen. Wanneer
     * 'r voor een slot geen actieve vraag is (gemeente heeft minder dan
     * 10 vragen, of die positie is inactief), blijft de Radio hidden.
     *
     * Cascade-show: vraag N is alleen zichtbaar als alle voorgaande
     * vragen 1..N-1 op 'Ja' staan. Eén 'Nee' → cascade stopt en
     * `isVergunningaanvraag` slaat aan via FormDerivedState.
     *
     * Hele lijst is hidden wanneer de gemeente nog op het oude systeem
     * zit (`use_new_report_questions === false`).
     *
     * @return list<Radio>
     */
    private static function reportQuestionRadios(): array
    {
        $radios = [];
        for ($i = 1; $i <= 10; $i++) {
            $key = sprintf('reportQuestion_%d', $i);
            $index = $i - 1;
            $position = $i;
            $radios[] = Radio::make($key)
                ->label(fn ($livewire): string => self::reportQuestionLabel($livewire->state(), $index))
                ->options(JaNeeOptions::OPTIONS)
                ->required()
                ->hidden(fn (Get $get, $livewire): bool => self::reportQuestionHidden($livewire->state(), $get, $i, $index))
                ->live()
                ->afterStateUpdated(function ($state, Set $set) use ($position) {
                    if ($state !== 'Ja') {
                        self::resetReportQuestionCascade($position, $set);
                    }
                });
        }

        return $radios;
    }

    private static function reportQuestionLabel(FormState $state, int $index): string
    {
        $questions = $state->get('gemeenteVariabelen.report_questions');
        if (! is_array($questions) || ! isset($questions[$index]['question'])) {
            return '';
        }

        return (string) $questions[$index]['question'];
    }

    private static function reportQuestionHidden(FormState $state, Get $get, int $position, int $index): bool
    {
        // Niet-actief nieuw systeem? Hele lijst verbergen.
        if ($state->get('gemeenteVariabelen.use_new_report_questions') !== true) {
            return true;
        }

        $questions = $state->get('gemeenteVariabelen.report_questions');
        if (! is_array($questions) || ! isset($questions[$index]['question'])) {
            return true; // geen vraag op deze positie → slot blijft leeg
        }

        // Cascade: vraag N alleen als 1..N-1 allemaal 'Ja' zijn.
        for ($prev = 1; $prev < $position; $prev++) {
            if ($get(sprintf('reportQuestion_%d', $prev)) !== 'Ja') {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper voor de legacy-radios om verborgen te raken zodra de
     * gemeente naar het nieuwe systeem is overgeschakeld. Bestaande
     * `isFieldHidden`-checks blijven leidend voor het oude pad.
     */
    private static function legacySysteemActief(FormState $state): bool
    {
        return $state->get('gemeenteVariabelen.use_new_report_questions') !== true;
    }

    /**
     * `contentGoNext` (= "vergunning noodzakelijk") tonen zodra duidelijk
     * is dat de aanvraag een vergunning wordt. In het oude pad bepaalt
     * FormFieldVisibility dat via de scan-cascade; in het nieuwe pad
     * leunt 't op de afgeleide `isVergunningaanvraag`-flag.
     */
    public static function contentGoNextHidden(FormState $state): bool
    {
        if ($state->get('gemeenteVariabelen.use_new_report_questions') === true) {
            return $state->get('isVergunningaanvraag') !== true;
        }

        return $state->isFieldHidden('contentGoNext') !== false;
    }

    /**
     * `MeldingTekst` (= "melding volstaat") verschijnt pas wanneer alle
     * actieve scan-vragen positief beantwoord zijn. In het oude pad regelt
     * FormFieldVisibility dat; in het nieuwe pad checken we expliciet of
     * álle `reportQuestion_N` op 'Ja' staan voor de gemeente-config.
     */
    public static function meldingTekstHidden(FormState $state): bool
    {
        if ($state->get('gemeenteVariabelen.use_new_report_questions') === true) {
            $questions = $state->get('gemeenteVariabelen.report_questions');
            if (! is_array($questions) || $questions === []) {
                return true;
            }
            foreach ($questions as $index => $_question) {
                $position = (int) $index + 1;
                if ($state->get(sprintf('reportQuestion_%d', $position)) !== 'Ja') {
                    return true;
                }
            }

            return false; // alle vragen Ja → toon melding-tekst
        }

        return $state->isFieldHidden('MeldingTekst') !== false;
    }

    /**
     * Volgorde van de legacy cascade-radios. Wanneer een vraag op iets
     * anders dan 'Ja' wordt gezet, worden alle daarop volgende radios
     * door {@see resetLegacyCascade()} op null gezet — anders blijven
     * eerder ingevulde antwoorden in state hangen ook al zijn de
     * cascade-vragen visueel verborgen.
     */
    private const LEGACY_CASCADE = [
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
        'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen',
        'WordtErAlleenMuziekGeluidGeproduceerdTussen',
        'IsdeGeluidsproductieLagerDan',
        'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten',
        'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst',
        'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
        'meldingvraag1',
        'meldingvraag2',
        'meldingvraag3',
        'meldingvraag4',
        'meldingvraag5',
        // `wordenErGebiedsontsluitings…` staat buiten de cascade — die
        // is altijd zichtbaar (laatste filter-vraag) en heeft geen
        // volgende om te resetten.
    ];

    /**
     * Reset alle cascade-velden NA `$currentKey` op null. Wordt door
     * `->afterStateUpdated()` aangeroepen wanneer een radio op iets
     * anders dan 'Ja' wordt gezet.
     */
    public static function resetLegacyCascade(string $currentKey, Set $set): void
    {
        $position = array_search($currentKey, self::LEGACY_CASCADE, true);
        if ($position === false) {
            return;
        }
        for ($i = $position + 1; $i < count(self::LEGACY_CASCADE); $i++) {
            $set(self::LEGACY_CASCADE[$i], null);
        }
    }

    /**
     * Reset reportQuestion_(N+1)..10 op null wanneer reportQuestion_N
     * op iets anders dan 'Ja' wordt gezet. Spiegel van
     * `resetLegacyCascade()` voor het nieuwe ReportQuestion-systeem.
     */
    public static function resetReportQuestionCascade(int $currentPosition, Set $set): void
    {
        for ($i = $currentPosition + 1; $i <= 10; $i++) {
            $set(sprintf('reportQuestion_%d', $i), null);
        }
    }
}
