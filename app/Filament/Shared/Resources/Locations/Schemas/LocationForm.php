<?php

namespace App\Filament\Shared\Resources\Locations\Schemas;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

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
                            ->required(),
                        TextInput::make('house_number')
                            ->label(__('resources/location.form.house_number.label'))
                            ->columnSpan(3)
                            ->required(),
                        TextInput::make('house_letter')
                            ->label(__('resources/location.form.house_letter.label'))
                            ->columnSpan(3),
                        TextInput::make('house_number_addition')
                            ->label(__('resources/location.form.house_number_addition.label'))
                            ->columnSpan(3),
                    ]),
                TextInput::make('street_name')
                    ->label(__('resources/location.form.street_name.label'))
                    ->required(),
                TextInput::make('city_name')
                    ->label(__('resources/location.form.city_name.label')),
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
                    ->extraStyles([
                        'min-height: 30rem',
                        'border-radius: 0.5rem',
                    ]),
            ]);
    }
}
