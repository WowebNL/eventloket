<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
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
                        Radio::make('isHetAantalAanwezigenBijUwEvenementMinderDanSdf')
                            ->label('Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen \'aanwezigen\' %} personen?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
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
                            ->live(),
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
                            ->live(),
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
                            ->live(),
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
                            ->live(),
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
                            ->live(),
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
                            ->live(),
                        Radio::make('meldingvraag1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_1 }}', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag1') !== false)
                            ->live(),
                        Radio::make('meldingvraag2')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_2 }}', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag2') !== false)
                            ->live(),
                        Radio::make('meldingvraag3')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_3 }}', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag3') !== false)
                            ->live(),
                        Radio::make('meldingvraag4')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_4 }}', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag4') !== false)
                            ->live(),
                        Radio::make('meldingvraag5')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('{{ gemeenteVariabelen.report_question_5 }}', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('meldingvraag5') !== false)
                            ->live(),
                        Radio::make('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer')
                            ->label('Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') !== false)
                            ->live(),
                        TextEntry::make('contentGoNext')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor uw evenement is een vergunning noodzakelijk. U wordt in Evenloket doorgeleid naar de vragen voor het aanvragen van een vergunning voor uw evenement.</p>', $livewire->state())))
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentGoNext') !== false),
                        TextEntry::make('MeldingTekst')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor uw evenement is geen vergunning noodzakelijk, maar is een melding voldoende. U wordt in Eventloket doorgeleid naar de vragen voor het indienen van een melding.</p>', $livewire->state())))
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('MeldingTekst') !== false),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('algemeneVragen') !== false),
            ]);
    }
}
