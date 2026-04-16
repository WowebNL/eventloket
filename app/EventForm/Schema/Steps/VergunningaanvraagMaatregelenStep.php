<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
    public const UUID = '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99';

    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: maatregelen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Aanpassen locatie en/of verwijderen straatmeubilair')
                    ->schema([
                        TextEntry::make('content29')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangekruisd: (Laten) aanpassen locatie en/of verwijderen straatmeubilair.</p>', $livewire->state()))),
                        Textarea::make('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen.', $livewire->state()))
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('aanpassenLocatieEnOfVerwijderenStraatmeubilair') !== false),
                Fieldset::make('Extra afval')
                    ->schema([
                        TextEntry::make('content30')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p><strong>U heeft aangegeven, dat er extra afval ontstaat op uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Hieronder volgen een aantal vragen daarover.</strong></p>', $livewire->state()))),
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
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen?', $livewire->state()))
                            ->numeric()
                            ->required(),
                        Radio::make('doetUAanAfvalscheidingOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required(),
                        Radio::make('voertUDeSchoonmaakZelfUit')
                            ->label('Voert u de schoonmaak zelf uit? ')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
                        FileUpload::make('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen')
                            ->label('U kunt het afvalplan hier uploaden of later als bijlage toevoegen.')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('voertUDeSchoonmaakZelfUit') === 'Ja');
                            }),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('extraAfval') !== false),
                Fieldset::make('Gemeentelijke hulpmiddelen')
                    ->schema([
                        Radio::make('wilUGebruikMakenVanGemeentelijkeHulpmiddelen')
                            ->label('Wil U gebruik maken van gemeentelijke hulpmiddelen?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
                        Fieldset::make('Veldengroep')
                            ->schema([
                                TextEntry::make('content37')
                                    ->hiddenLabel()
                                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Vermeld hier van welke materialen u gebruik zou willen maken en ook de aantallen. Uw betreffende gemeente zal aangeven welke hulpmiddelen aangeboden kunnen worden.</p>', $livewire->state()))),
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
                                    ->options([
                                        'Ja' => 'Ja',
                                        'Nee' => 'Nee',
                                    ])
                                    ->required(),
                                Textarea::make('geefAanOpWelkeLocatieUStroomWilt1')
                                    ->label('Geef aan op welke locatie u stroom wilt afnemen')
                                    ->required()
                                    ->maxLength(10000)
                                    ->hidden(function (Get $get, $livewire): bool {
                                        $rule = $livewire->state()->isFieldHidden('geefAanOpWelkeLocatieUStroomWilt1');
                                        if ($rule !== null) {
                                            return $rule;
                                        }

                                        return ! ($get('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente') === 'Ja');
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('veldengroep2');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('wilUGebruikMakenVanGemeentelijkeHulpmiddelen') === 'Ja');
                            }),
                    ]),
            ]);
    }
}
