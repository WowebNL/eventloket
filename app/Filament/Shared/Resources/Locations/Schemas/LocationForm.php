<?php

namespace App\Filament\Shared\Resources\Locations\Schemas;

use Closure;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/location.form.name.label'))
                    ->required(),
                Grid::make(12)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('postal_code')
                            ->label(__('resources/location.form.postal_code.label'))
                            ->columnSpan(3)
                            ->maxLength(6)
                            ->regex('/^[1-9][0-9]{3}[A-Z]{2}$/')
                            ->validationAttribute(__('resources/location.form.postal_code.label'))
                            ->placeholder('1234AB')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, Component $livewire, $state) {
                                // Remove spaces and convert to uppercase
                                $state = strtoupper(str_replace(' ', '', $state ?? ''));
                                $set('postal_code', $state);

                                // Auto-fill address if postal code and house number are filled
                                static::fetchAddressData($get, $set, $livewire);
                            }),
                        TextInput::make('house_number')
                            ->label(__('resources/location.form.house_number.label'))
                            ->columnSpan(3)
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, Component $livewire) => static::fetchAddressData($get, $set, $livewire)),
                        TextInput::make('house_letter')
                            ->label(__('resources/location.form.house_letter.label'))
                            ->columnSpan(3)
                            ->maxLength(255),
                        TextInput::make('house_number_addition')
                            ->label(__('resources/location.form.house_number_addition.label'))
                            ->columnSpan(3)
                            ->maxLength(255),
                    ]),
                TextInput::make('street_name')
                    ->label(__('resources/location.form.street_name.label'))
                    ->maxLength(255),
                TextInput::make('city_name')
                    ->label(__('resources/location.form.city_name.label'))
                    ->maxLength(255),
                Toggle::make('active')
                    ->label(__('resources/location.form.active.label'))
                    ->helperText(__('resources/location.form.active.helper_text'))
                    ->default(true)
                    ->required(),

                Map::make('geometry')
                    ->label(__('resources/location.form.geometry.label'))
                    ->columnSpanFull()
                    ->defaultLocation(latitude: 52.144559, longitude: 5.173777)
                    ->zoom(7)
                    ->showMarker(false)
                    ->geoMan(true)
                    ->geoManPosition('topright')
                    ->drawText(false)
                    ->maxZoom(19) // Prevents gray maps on edit
                    ->extraStyles([
                        'min-height: 30rem',
                        'border-radius: 0.5rem',
                    ])
                    ->required()
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                            if (! isset($value['geojson']['features']) || empty($value['geojson']['features'])) {
                                $fail(__('resources/location.form.geometry.validation.geojson_required'));
                            }
                        },
                    ]),
            ]);
    }

    protected static function fetchAddressData(Get $get, Set $set, Component $livewire): void
    {
        $postalCode = $get('postal_code');
        $houseNumber = $get('house_number');

        // Only fetch if we have both postal code and house number
        if (! $postalCode || ! $houseNumber) {
            return;
        }

        // Validate postal code format (6 characters, no spaces)
        if (! preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postalCode)) {
            return;
        }

        try {
            // Call PDOK Locatieserver API
            $response = Http::timeout(10)
                ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', [
                    'q' => "postcode:$postalCode+AND+huisnummer:$houseNumber",
                    'rows' => 1,
                ]);

            if ($response->successful() && $response->json('response.numFound') > 0) {
                $result = $response->json('response.docs.0');

                // Set street name and city
                $set('street_name', $result['straatnaam'] ?? '');
                $set('city_name', $result['woonplaatsnaam'] ?? '');

                // Set coordinates on map if available
                if (isset($result['centroide_ll'])) {
                    $coords = explode(' ', str_replace(')', '', str_replace('POINT(', '', $result['centroide_ll'])));
                    if (count($coords) === 2) {

                        $lat = (float) $coords[1];
                        $lng = (float) $coords[0];

                        $set('geometry', [
                            'lat' => $lat,
                            'lng' => $lng,
                            'geojson' => [
                                'features' => [
                                    [
                                        'properties' => [],
                                        'type' => 'Feature',
                                        'geometry' => [
                                            'coordinates' => [$lng, $lat],
                                            'type' => 'Point',
                                        ],
                                    ],
                                ],
                                'type' => 'FeatureCollection',
                            ],
                        ]);

                        $livewire->dispatch('refreshMap');
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - user can manually enter address
            logger()->warning('Address lookup failed', [
                'postal_code' => $postalCode,
                'house_number' => $houseNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
