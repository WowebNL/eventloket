<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

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
                    ->schema([
                        TextEntry::make('content27')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er diverse voorwerpen geplaatst worden. Wilt u hier de aantallen en locaties (indien meerdere) invullen?</p>', $livewire->state()))),
                        Repeater::make('verkooppuntenToegangsKaarten')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('verkooppuntenToegangsKaarten') !== false),
                        Repeater::make('verkooppuntenMuntenEnBonnen')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('verkooppuntenMuntenEnBonnen') !== false),
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('verkooppuntenCashless') !== false),
                        Repeater::make('Speeltoestellen')
                            ->label('Speeltoestellen')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('Speeltoestellen') !== false),
                        Repeater::make('brandstofopslag')
                            ->label('Brandstofopslag')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('brandstofopslag') !== false),
                        Repeater::make('geluidstorens')
                            ->label('Geluidstorens')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('geluidstorens') !== false),
                        Repeater::make('Lichtmasten')
                            ->label('Lichtmasten')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('Lichtmasten') !== false),
                        Repeater::make('marktkramen')
                            ->label('Marktkramen')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('marktkramen') !== false),
                        Repeater::make('andersGroup')
                            ->label('Anders')
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('andersGroup') !== false),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('voorwerpen') !== false),
                Fieldset::make('Brandgevaarlijke stoffen')
                    ->schema([
                        TextEntry::make('content28')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er sprake is van Aggregaten,&nbsp; brandstofopslag en andere brandgevaarlijke stoffen. Denk aan :</p><ul><li>Aggregaten</li><li>Brandstofopslag</li><li>Gasflessen</li><li>Frituur</li><li>Houtskoolbarbecue</li><li>Open vuur (vuurplaats, vuurkorven)</li><li>Vuurwerk</li><li>Carbid-, kanon- en kamerschieten</li><li>Materiaal voor showeffecten</li></ul>', $livewire->state()))),
                        Repeater::make('welkeStoffenGebruiktU')
                            ->label('Welke stoffen gebruikt u?')
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
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('brandgevaarlijkeStoffen') !== false),
            ]);
    }
}
