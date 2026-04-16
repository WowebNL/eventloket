<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 8a5fb30f-287e-41a2-a9bc-e7340bdaaa99
 *
 * @openforms-step-index 12
 */
final class VergunningaanvraagMaatregelenStep
{
    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: maatregelen')
            ->schema([
                Fieldset::make('Aanpassen locatie en/of verwijderen straatmeubilair')
                    ->schema([
                        Placeholder::make('content29')
                            ->content(new HtmlString('<p>U heeft aangekruisd: (Laten) aanpassen locatie en/of verwijderen straatmeubilair.</p>')),
                        Textarea::make('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen')
                            ->label('Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen.')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(),
                Fieldset::make('Extra afval')
                    ->schema([
                        Placeholder::make('content30')
                            ->content(new HtmlString('<p><strong>U heeft aangegeven, dat er extra afval ontstaat op uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Hieronder volgen een aantal vragen daarover.</strong></p>')),
                        Repeater::make('wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat')
                            ->label('Wie maakt de locaties en de omgeving daarvan schoon, en wanneer gebeurt dat?')
                            ->schema([
                                TextInput::make('locatieAfval')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('doorWieAfval')
                                    ->label('Door wie?')
                                    ->required()
                                    ->maxLength(1000),
                                DateTimePicker::make('starttijdSchoonmaak')
                                    ->label('Starttijd schoonmaak')
                                    ->required(),
                                DateTimePicker::make('eindtijdSchoonmaak')
                                    ->label('Eindtijd schoonmaak')
                                    ->required(),
                            ]),
                        TextInput::make('hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen')
                            ->label('Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen?')
                            ->numeric()
                            ->required(),
                        Radio::make('doetUAanAfvalscheidingOpLocatieEvenementX')
                            ->label('Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required(),
                        Radio::make('voertUDeSchoonmaakZelfUit')
                            ->label('Voert u de schoonmaak zelf uit? ')
                            ->required(),
                        FileUpload::make('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen')
                            ->label('U kunt het afvalplan hier uploaden of later als bijlage toevoegen.')
                            ->visible(fn (Get $get): bool => $get('voertUDeSchoonmaakZelfUit') === 'Ja'),
                    ])
                    ->hidden(),
                Fieldset::make('Gemeentelijke hulpmiddelen')
                    ->schema([
                        Radio::make('wilUGebruikMakenVanGemeentelijkeHulpmiddelen')
                            ->label('Wil U gebruik maken van gemeentelijke hulpmiddelen?')
                            ->required(),
                        Fieldset::make('Veldengroep')
                            ->schema([
                                Placeholder::make('content37')
                                    ->content(new HtmlString('<p>Vermeld hier van welke materialen u gebruik zou willen maken en ook de aantallen. Uw betreffende gemeente zal aangeven welke hulpmiddelen aangeboden kunnen worden.</p>')),
                                TextInput::make('dranghekken1')
                                    ->label('Dranghekken')
                                    ->numeric(),
                                TextInput::make('wegafzettingen1')
                                    ->label('Wegafzettingen')
                                    ->numeric(),
                                TextInput::make('vlaggen1')
                                    ->label('Vlaggen')
                                    ->numeric(),
                                TextInput::make('vlaggenmasten1')
                                    ->label('Vlaggenmasten')
                                    ->numeric(),
                                TextInput::make('parkeerverbodsborden1')
                                    ->label('Parkeerverbodsborden')
                                    ->numeric(),
                                TextInput::make('bordenGeslotenVerklaring1')
                                    ->label('Borden gesloten verklaring')
                                    ->numeric(),
                                TextInput::make('bordenEenrichtingsweg1')
                                    ->label('Borden eenrichtingsweg')
                                    ->numeric(),
                                Radio::make('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente1')
                                    ->label('Wenst u tegen betaling stroom af te nemen van de gemeente?')
                                    ->required(),
                                Textarea::make('geefAanOpWelkeLocatieUStroomWilt1')
                                    ->label('Geef aan op welke locatie u stroom wilt afnemen')
                                    ->required()
                                    ->maxLength(10000)
                                    ->visible(fn (Get $get): bool => $get('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente') === 'Ja'),
                            ])
                            ->visible(fn (Get $get): bool => $get('wilUGebruikMakenVanGemeentelijkeHulpmiddelen') === 'Ja'),
                    ]),
            ]);
    }
}
