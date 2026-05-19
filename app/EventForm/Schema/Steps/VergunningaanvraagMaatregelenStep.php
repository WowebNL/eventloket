<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

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
                    ->columns(1)
                    ->schema([
                        InfoText::info('content29', '<p>U heeft aangekruisd: (Laten) aanpassen locatie en/of verwijderen straatmeubilair.</p>'),
                        Textarea::make('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen.', $livewire->state()))
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('aanpassenLocatieEnOfVerwijderenStraatmeubilair') !== false),
                Fieldset::make('Extra afval')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content30', '<p><strong>U heeft aangegeven, dat er extra afval ontstaat op uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Hieronder volgen een aantal vragen daarover.</strong></p>'),
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
                                    ->seconds(false)
                                    ->required(),
                                DateTimePicker::make('eindtijdSchoonmaak')
                                    ->label('Eindtijd schoonmaak')
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        TextInput::make('hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen?', $livewire->state()))
                            ->numeric()
                            ->required(),
                        Radio::make('doetUAanAfvalscheidingOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required(),
                        Radio::make('voertUDeSchoonmaakZelfUit')
                            ->label('Voert u de schoonmaak zelf uit? ')
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        FileUpload::make('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen')
                            ->label('U kunt het afvalplan hier uploaden of later als bijlage toevoegen.')
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                ' In het afvalplan vertelt u welke maatregelen u neemt om afval te voorkomen en te beperken. Daarnaast geeft u aan, welke afvalstromen er zijn en hoe deze verwerkt en opgeruimd worden.',
                            ])
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('voertUDeSchoonmaakZelfUit') === 'Ja');
                            }),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('extraAfval') !== false),
            ]);
    }
}
