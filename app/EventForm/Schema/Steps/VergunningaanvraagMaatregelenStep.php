<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\EventDateTimePicker;
use App\EventForm\Components\EventloketFileUpload;
use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use App\Models\Organisation;
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

    public static function make(?Organisation $organisation = null): Step
    {
        return Step::make('Vergunningaanvraag: maatregelen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Aanpassen locatie en/of verwijderen straatmeubilair')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content29', '<p>U heeft aangekruisd: (Laten) aanpassen locatie en/of verwijderen straatmeubilair.</p>'),
                        Textarea::make('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen')
                            ->label(Label::render('Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen.'))
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(Hidden::rule('aanpassenLocatieEnOfVerwijderenStraatmeubilair')),
                Fieldset::make('Extra afval')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content30', '<p><strong>U heeft aangegeven, dat er extra afval ontstaat op uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Hieronder volgen een aantal vragen daarover.</strong></p>'),
                        Repeater::make('wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat')
                            ->label('Wie maakt de locaties en de omgeving daarvan schoon, en wanneer gebeurt dat?')
                            ->addActionLabel('Nog een schoonmaakmoment toevoegen')
                            ->schema([
                                TextInput::make('locatieAfval')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('doorWieAfval')
                                    ->label('Door wie?')
                                    ->required()
                                    ->maxLength(1000),
                                EventDateTimePicker::make('starttijdSchoonmaak')
                                    ->label('Starttijd schoonmaak')
                                    ->seconds(false)
                                    ->required(),
                                EventDateTimePicker::make('eindtijdSchoonmaak')
                                    ->label('Eindtijd schoonmaak')
                                    ->seconds(false)
                                    ->afterOrEqual('starttijdSchoonmaak')
                                    ->validationMessages([
                                        'after_or_equal' => 'De eindtijd van de schoonmaak moet op of na de starttijd liggen.',
                                    ])
                                    ->required(),
                            ]),
                        TextInput::make('hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen')
                            ->label(Label::render('Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen?'))
                            ->numeric()
                            ->required(),
                        Radio::make('doetUAanAfvalscheidingOpLocatieEvenementX')
                            ->label(Label::render('Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required(),
                        Radio::make('voertUDeSchoonmaakZelfUit')
                            ->label('Voert u de schoonmaak zelf uit? ')
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        EventloketFileUpload::make('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen', $organisation)
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
                    ->hidden(Hidden::rule('extraAfval')),
            ]);
    }
}
