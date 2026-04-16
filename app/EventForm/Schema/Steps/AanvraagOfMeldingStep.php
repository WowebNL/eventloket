<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid d87c01ce-8387-43b0-a8c8-e6cf5abb6da1
 *
 * @openforms-step-index 5
 */
final class AanvraagOfMeldingStep
{
    public static function make(): Step
    {
        return Step::make('Vergunningsplichtig scan')
            ->schema([
                Placeholder::make('content4')
                    ->content(new HtmlString('<p>Wij stellen u enkele vragen over uw evenement om te beoordelen of uw evenement meldingsplichtig of vergunningsplichtig is.</p>')),
                Placeholder::make('contentGemeenteMelding')
                    ->content(new HtmlString('<p>Uw evenement vindt plaats binnen de gemeente: <strong>{% get_value evenementInGemeente \'name\' %}</strong></p>')),
                Fieldset::make('Algemene vragen')
                    ->schema([
                        Radio::make('isHetAantalAanwezigenBijUwEvenementMinderDanSdf')
                            ->label('Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen \'aanwezigen\' %} personen?')
                            ->required(),
                        Radio::make('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen')
                            ->label('Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?')
                            ->required(),
                        Radio::make('WordtErAlleenMuziekGeluidGeproduceerdTussen')
                            ->label('Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?')
                            ->required(),
                        Radio::make('IsdeGeluidsproductieLagerDan')
                            ->label('Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?')
                            ->required(),
                        Radio::make('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten')
                            ->label('Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?')
                            ->required(),
                        Radio::make('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst')
                            ->label('Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?')
                            ->required(),
                        Radio::make('indienErObjectenGeplaatstWordenZijnDezeDanKleiner')
                            ->label('Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? ')
                            ->required(),
                        Radio::make('meldingvraag1')
                            ->label('{{ gemeenteVariabelen.report_question_1 }}')
                            ->required(),
                        Radio::make('meldingvraag2')
                            ->label('{{ gemeenteVariabelen.report_question_2 }}')
                            ->required(),
                        Radio::make('meldingvraag3')
                            ->label('{{ gemeenteVariabelen.report_question_3 }}')
                            ->required(),
                        Radio::make('meldingvraag4')
                            ->label('{{ gemeenteVariabelen.report_question_4 }}')
                            ->required(),
                        Radio::make('meldingvraag5')
                            ->label('{{ gemeenteVariabelen.report_question_5 }}')
                            ->required(),
                        Radio::make('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer')
                            ->label('Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?')
                            ->required(),
                        Placeholder::make('contentGoNext')
                            ->content(new HtmlString('<p>Voor uw evenement is een vergunning noodzakelijk. U wordt in Evenloket doorgeleid naar de vragen voor het aanvragen van een vergunning voor uw evenement.</p>')),
                        Placeholder::make('MeldingTekst')
                            ->content(new HtmlString('<p>Voor uw evenement is geen vergunning noodzakelijk, maar is een melding voldoende. U wordt in Eventloket doorgeleid naar de vragen voor het indienen van een melding.</p>')),
                    ]),
            ]);
    }
}
