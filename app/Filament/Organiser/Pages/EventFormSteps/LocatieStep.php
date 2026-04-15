<?php

namespace App\Filament\Organiser\Pages\EventFormSteps;

use App\Models\Municipality;
use Closure;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class LocatieStep
{
    public static function make(): Step
    {
        return Step::make('Locatie')
            ->icon('heroicon-o-map-pin')
            ->schema([
                CheckboxList::make('locatie_type')
                    ->label('Waar vindt het evenement plaats?')
                    ->options([
                        'gebouw' => 'In een gebouw of meerdere gebouwen',
                        'buiten' => 'Buiten op één of meerdere plaatsen',
                        'route' => 'Op een route',
                    ])
                    ->required()
                    ->live(),

                // Buiten locatie
                Fieldset::make('Buiten locatie')
                    ->schema([
                        TextInput::make('buiten_locatie_naam')
                            ->label('Naam van de locatie')
                            ->required(),

                        Map::make('buiten_locatie_kaart')
                            ->label('Locatie op de kaart')
                            ->columnSpanFull()
                            ->defaultLocation(latitude: 50.85, longitude: 5.69)
                            ->zoom(12)
                            ->showMarker(false)
                            ->geoMan(true)
                            ->geoManPosition('topright')
                            ->drawText(false)
                            ->maxZoom(19)
                            ->extraStyles([
                                'min-height: 25rem',
                                'border-radius: 0.5rem',
                            ])
                            ->required()
                            ->rules([
                                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                    if (! isset($value['geojson']['features']) || empty($value['geojson']['features'])) {
                                        $fail('Teken de locatie in op de kaart.');
                                    }
                                },
                            ]),
                    ])
                    ->visible(fn (Get $get) => in_array('buiten', $get('locatie_type') ?? [])),

                // Gebouw locatie
                Fieldset::make('Gebouw locatie')
                    ->schema([
                        TextInput::make('gebouw_naam')
                            ->label('Naam van de locatie')
                            ->required(),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('gebouw_postcode')
                                    ->label('Postcode')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set, Component $livewire) => static::fetchGebouwAddress($get, $set)),
                                TextInput::make('gebouw_huisnummer')
                                    ->label('Huisnummer')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::fetchGebouwAddress($get, $set)),
                                TextInput::make('gebouw_huisletter')
                                    ->label('Huisletter'),
                                TextInput::make('gebouw_toevoeging')
                                    ->label('Toevoeging'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gebouw_straat')
                                    ->label('Straatnaam')
                                    ->required(),
                                TextInput::make('gebouw_plaats')
                                    ->label('Plaatsnaam')
                                    ->required(),
                            ]),
                    ])
                    ->visible(fn (Get $get) => in_array('gebouw', $get('locatie_type') ?? [])),

                // Gemeente selectie
                Select::make('gemeente')
                    ->label('In welke gemeente vindt het evenement plaats?')
                    ->options(fn () => Municipality::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => ! empty($get('locatie_type'))),
            ]);
    }

    protected static function fetchGebouwAddress(Get $get, Set $set): void
    {
        $postcode = strtoupper(str_replace(' ', '', $get('gebouw_postcode') ?? ''));
        $huisnummer = $get('gebouw_huisnummer');

        if (! $postcode || ! $huisnummer || ! preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postcode)) {
            return;
        }

        try {
            $response = Http::timeout(10)->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', [
                'q' => "postcode:{$postcode}+AND+huisnummer:{$huisnummer}",
                'rows' => 1,
            ]);

            if ($response->successful() && $response->json('response.numFound') > 0) {
                $result = $response->json('response.docs.0');
                $set('gebouw_straat', $result['straatnaam'] ?? '');
                $set('gebouw_plaats', $result['woonplaatsnaam'] ?? '');
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
