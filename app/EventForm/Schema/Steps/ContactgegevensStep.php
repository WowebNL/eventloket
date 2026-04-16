<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5
 *
 * @openforms-step-index 0
 */
final class ContactgegevensStep
{
    public static function make(): Step
    {
        return Step::make('Contactgegevens')
            ->schema([
                Placeholder::make('loadUserInformation')
                    ->content(new HtmlString('<p>Een ogenblik geduld, uw gegevens worden ingeladen…</p>')),
                TextInput::make('watIsUwVoornaam')
                    ->label('Wat is uw voornaam?')
                    ->required()
                    ->maxLength(1000),
                TextInput::make('watIsUwAchternaam')
                    ->label('Wat is uw achternaam?')
                    ->required()
                    ->maxLength(1000),
                TextInput::make('watIsUwEMailadres')
                    ->label('Wat is uw e-mailadres?')
                    ->email()
                    ->required(),
                TextInput::make('watIsUwTelefoonnummer')
                    ->label('Wat is uw telefoonnummer?')
                    ->required()
                    ->maxLength(1000),
                Fieldset::make('Organisatie informatie')
                    ->schema([
                        TextInput::make('watIsHetKamerVanKoophandelNummerVanUwOrganisatie')
                            ->label('Wat is het Kamer van Koophandel nummer van uw organisatie?')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('watIsDeNaamVanUwOrganisatie')
                            ->label('Wat is de naam van uw organisatie?')
                            ->required()
                            ->maxLength(1000),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('postcode1')
                                    ->label('Postcode')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('huisletter1')
                                    ->label('Huisletter')
                                    ->maxLength(1000),
                                TextInput::make('straatnaam1')
                                    ->label('Straatnaam')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('huisnummer1')
                                    ->label('Huisnummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('huisnummertoevoeging1')
                                    ->label('Huisnummertoevoeging')
                                    ->maxLength(1000),
                                TextInput::make('plaatsnaam1')
                                    ->label('Plaatsnaam')
                                    ->required()
                                    ->maxLength(1000),
                            ]),
                        TextInput::make('emailadresOrganisatie')
                            ->label('Wat is het e-mailadres van uw organisatie?')
                            ->email(),
                        TextInput::make('telefoonnummerOrganisatie')
                            ->label('Wat is het telefoonnummer van uw organisatie?')
                            ->required()
                            ->maxLength(1000),
                    ]),
                Placeholder::make('waarschuwingGeenKvk')
                    ->content(new HtmlString('<p><strong>Let op: </strong>u vult dit formulier in op persoonlijke titel, hiermee ligt de verantwoordelijkheid voor de aanvraag ook bij u als persoon. U kunt deze aanvraag als bedrijf doen door linksboven op “Mijn omgeving” te klikken en een organisatie te registeren (of een bestaande te selecteren), vervolgens kunt u een nieuwe aanvraag starten.</p>')),
                Fieldset::make('Adresgegevens')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('postcode')
                                    ->label('Postcode')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('huisletter')
                                    ->label('Huisletter')
                                    ->maxLength(1000),
                                TextInput::make('straatnaam')
                                    ->label('Straatnaam')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('land')
                                    ->label('Land')
                                    ->maxLength(1000),
                                TextInput::make('huisnummer')
                                    ->label('Huisnummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('huisnummertoevoeging')
                                    ->label('Huisnummertoevoeging')
                                    ->maxLength(1000),
                                TextInput::make('plaatsnaam')
                                    ->label('Plaatsnaam')
                                    ->required()
                                    ->maxLength(1000),
                            ]),
                    ]),
                CheckboxList::make('extraContactpersonenToevoegen')
                    ->label('Extra contactpersonen toevoegen')
                    ->options([
                        'vooraf' => 'Contactpersoon voorafgaand aan het evenement',
                        'tijdens' => 'Contactpersoon tijdens het evenement',
                        'achteraf' => 'Contactpersoon na het evenement',
                    ]),
                Fieldset::make('Contactpersoon voorafgaand aan het evenement')
                    ->schema([
                        TextInput::make('naam')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('telefoonnummer')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required(),
                        TextInput::make('eMailadres')
                            ->label('E-mailadres')
                            ->email()
                            ->required(),
                    ]),
                Fieldset::make('Contactpersoon tijdens het evenement')
                    ->schema([
                        TextInput::make('naam1')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('telefoonnummer1')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required(),
                        TextInput::make('eMailadres1')
                            ->label('E-mailadres')
                            ->email()
                            ->required(),
                    ]),
                Fieldset::make('Contactpersoon na het evenement')
                    ->schema([
                        TextInput::make('naam2')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('telefoonnummer2')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required(),
                        TextInput::make('eMailadres2')
                            ->label('E-mailadres')
                            ->email()
                            ->required(),
                    ]),
            ]);
    }
}
