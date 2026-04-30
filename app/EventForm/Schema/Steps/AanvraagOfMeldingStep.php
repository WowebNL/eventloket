<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\State\FormState;
use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

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
                TextEntry::make('content4')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Wij stellen u enkele vragen over uw evenement om te beoordelen of uw evenement meldingsplichtig of vergunningsplichtig is.</p>', $livewire->state()))),
                TextEntry::make('contentGemeenteMelding')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Uw evenement vindt plaats binnen de gemeente: <strong>{% get_value evenementInGemeente \'name\' %}</strong></p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentGemeenteMelding') !== false),
                Fieldset::make('Algemene vragen')
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen \'aanwezigen\' %} personen?', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen')
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? ', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
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
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_1 }}', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag1') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag2')
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_2 }}', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag2') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag3')
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_3 }}', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag3') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag4')
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_4 }}', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag4') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('meldingvraag5')
                                ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_5 }}', $livewire->state()))
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag5') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            Radio::make('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer')
                                ->label('Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?')
                                ->options([
                                    'Ja' => 'Ja',
                                    'Nee' => 'Nee',
                                ])
                                ->required()
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') !== false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, $component) {
                                    if ($state !== 'Ja') {
                                        self::resetLegacyCascade($component->getName(), $set);
                                    }
                                }),
                            TextEntry::make('contentGoNext')
                                ->hiddenLabel()
                                ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor uw evenement is een vergunning noodzakelijk. U wordt in Evenloket doorgeleid naar de vragen voor het aanvragen van een vergunning voor uw evenement.</p>', $livewire->state())))
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentGoNext') !== false),
                            TextEntry::make('MeldingTekst')
                                ->hiddenLabel()
                                ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor uw evenement is geen vergunning noodzakelijk, maar is een melding voldoende. U wordt in Eventloket doorgeleid naar de vragen voor het indienen van een melding.</p>', $livewire->state())))
                                ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('MeldingTekst') !== false),
                        ])
                            ->hidden(fn ($livewire): bool => ! self::legacySysteemActief($livewire->state())),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('algemeneVragen') !== false),
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
                ->options([
                    'Ja' => 'Ja',
                    'Nee' => 'Nee',
                ])
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
