<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid 8facfe56-5548-44e7-93b9-1356bc266e00
 *
 * @openforms-step-index 4
 */
final class WaarvoorWiltUHetEventloketGebruikenStep
{
    public const UUID = '8facfe56-5548-44e7-93b9-1356bc266e00';

    public static function make(): Step
    {
        return Step::make('Vooraankondiging')
            ->key(self::UUID)
            ->schema([
                Radio::make('waarvoorWiltUEventloketGebruiken')
                    ->label('Waarvoor wilt u Eventloket gebruiken?')
                    ->options([
                        'evenement' => 'U wilt voor uw evementen een aanvraag indienen',
                        'vooraankondiging' => 'U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in',
                    ])
                    ->required()
                    ->live(),
                Fieldset::make('Vooraankondiging')
                    ->schema([
                        InfoText::info('content3', '<p>Een vooraankondiging biedt u de mogelijkheid om alvast een voorkeursdatum voor uw evenement te registreren in onze applicatie.</p><p>Een vooraankondiging zal tijdig omgezet moeten worden in een melding of vergunning, anders vervalt de aanvraag, als niet voldaan wordt aan de wettelijke doorlooptijden.</p><p>Voor een vooraankondiging dient u minimaal de volgende gegevens in te vullen:</p>'),
                        TextInput::make('aantalVerwachteAanwezigen')
                            ->label('Aantal verwachte aanwezigen')
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('vooraankondiginggroep') !== false),
            ]);
    }
}
