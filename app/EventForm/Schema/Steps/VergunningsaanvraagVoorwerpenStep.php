<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Schema\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid d790edb5-712a-4f83-87a8-1a86e4831455
 *
 * @openforms-step-index 11
 */
final class VergunningsaanvraagVoorwerpenStep
{
    public const UUID = 'd790edb5-712a-4f83-87a8-1a86e4831455';

    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: voorwerpen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Voorwerpen')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content27', '<p>U heeft aangegeven, dat er diverse voorwerpen geplaatst worden. Wilt u hier de aantallen en locaties (indien meerdere) invullen?</p>'),
                        Repeater::make('verkooppuntenToegangsKaarten')
                            ->minItems(1)
                            ->label('Verkooppunten toegangs-kaarten')
                            ->schema([
                                TextInput::make('locatieVerkooppuntToegangskaart')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantapVerkoopuntenToegangskaarten')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('verkooppuntenToegangsKaarten')),
                        Repeater::make('verkooppuntenMuntenEnBonnen')
                            ->minItems(1)
                            ->label('Verkooppunten munten en bonnen')
                            ->schema([
                                TextInput::make('locatieVerkooppuntMuntenBonnen')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantapVerkoopuntenMuntenBonnen')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('verkooppuntenMuntenEnBonnen')),
                        Repeater::make('verkooppuntenCashless')
                            ->label('Verkooppunten cashless')
                            ->schema([
                                TextInput::make('locatieVerkooppuntCashless')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantapVerkoopuntenCashless')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('verkooppuntenCashless')),
                        Repeater::make('Speeltoestellen')
                            ->label('Speeltoestellen')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatiespeeltoestellen')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalSpeeltoestellen')
                                    ->label('Aantal speeltoestellen')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('Speeltoestellen')),
                        Repeater::make('brandstofopslag')
                            ->label('Brandstofopslag')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatiebrandstofopslag')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalbrandstofopslag')
                                    ->label('Aantal brandstofopslag')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('brandstofopslag')),
                        Repeater::make('geluidstorens')
                            ->label('Geluidstorens')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatieGeluidstoren')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalGeluidstoren')
                                    ->label('Aantal geluidstorens')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('geluidstorens')),
                        Repeater::make('Lichtmasten')
                            ->label('Lichtmasten')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatieLichtmast')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalLichtmast')
                                    ->label('Aantal lichtmasten')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('Lichtmasten')),
                        Repeater::make('marktkramen')
                            ->label('Marktkramen')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatieMarktkraam')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalMarktkraam')
                                    ->label('Aantal marktkramen')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('marktkramen')),
                        Repeater::make('andersGroup')
                            ->label('Anders')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('locatieAnders')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('aantalAnders')
                                    ->label('Aantal anders')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(Hidden::rule('andersGroup')),
                    ])
                    ->hidden(Hidden::rule('voorwerpen')),
                Fieldset::make('Brandgevaarlijke stoffen')
                    ->schema([
                        InfoText::info('content28', '<p>U heeft aangegeven, dat er sprake is van Aggregaten,&nbsp; brandstofopslag en andere brandgevaarlijke stoffen. Denk aan :</p><ul><li>Aggregaten</li><li>Brandstofopslag</li><li>Gasflessen</li><li>Frituur</li><li>Houtskoolbarbecue</li><li>Open vuur (vuurplaats, vuurkorven)</li><li>Vuurwerk</li><li>Carbid-, kanon- en kamerschieten</li><li>Materiaal voor showeffecten</li></ul>'),
                        Repeater::make('welkeStoffenGebruiktU')
                            ->label('Welke stoffen gebruikt u?')
                            ->minItems(1)
                            ->schema([
                                TextInput::make('typeStof')
                                    ->label('Type stof')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('plaatsStof')
                                    ->label('Plaats')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('opslagwijzeStof')
                                    ->label('Opslagwijze')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('toelichtingStof')
                                    ->label('Toelichting')
                                    ->required()
                                    ->maxLength(1000),
                            ]),
                    ])
                    ->hidden(Hidden::rule('brandgevaarlijkeStoffen')),
            ]);
    }
}
