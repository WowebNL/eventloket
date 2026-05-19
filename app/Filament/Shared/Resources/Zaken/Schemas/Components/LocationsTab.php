<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas\Components;

use App\Models\Zaak;
use App\Filament\Infolists\GeoJsonMapEntry as MapEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Str;

class LocationsTab
{
    public static function make(): Tab
    {
        return Tab::make('locations-'.Str::uuid())
            ->label(__('municipality/resources/zaak.infolist.tabs.locations.label'))
            ->icon('heroicon-o-map-pin')
            ->columns(12)
            ->visible(fn (Zaak $record) => $record->openzaak)
            ->schema([
                Fieldset::make(__('municipality/resources/zaak.infolist.tabs.locations.information.label'))
                    ->schema([
                        TextEntry::make('openzaak.zaakAddresses')
                            ->label(__('municipality/resources/zaak.infolist.tabs.locations.information.address.label'))
                            ->listWithLineBreaks()
                            ->hidden(fn (?array $state) => empty($state)),
                        TextEntry::make('reference_data.naam_locatie_evenement')
                            ->label(__('municipality/resources/zaak.infolist.tabs.locations.information.location_name.label'))
                            ->hidden(fn (?string $state) => empty($state)),
                    ])
                    ->columns(1)
                    ->columnSpan(4),
                Fieldset::make(__('municipality/resources/zaak.infolist.tabs.locations.map.label'))
                    ->schema([
                        MapEntry::make('zaakgeometrie')
                            ->hiddenLabel()
                            ->geojsonData(fn (Zaak $record) => static::transformGeometry($record->openzaak->zaakgeometrie))
                            ->defaultLocation(50.8514, 5.6910)
                            ->extraAttributes(['class' => 'locaties-kaart'])
                            ->hidden(fn (Zaak $record) => empty($record->openzaak->zaakgeometrie)),
                    ])
                    ->columns(1)
                    ->columnSpan(8),
            ]);
    }

    protected static function transformGeometry(?array $geometry): ?array
    {
        if (empty($geometry['geometries'])) {
            return null;
        }

        $features = [];

        foreach ($geometry['geometries'] as $geom) {
            $title = match ($geom['type'] ?? '') {
                'LineString' => 'Route van het evenement',
                'Polygon'    => 'Buitenlocatie van het evenement',
                'Point'      => 'Adres van het evenement',
                default      => null,
            };

            $features[] = [
                'type'       => 'Feature',
                'properties' => ['title' => $title],
                'geometry'   => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
