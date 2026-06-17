<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas\Components;

use App\Filament\Infolists\GeoJsonMapEntry as MapEntry;
use App\Models\Zaak;
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
                            ->hidden(fn (?string $state, Zaak $record) => empty($state) || ! empty($record->reference_data->locaties_evenement)),
                        TextEntry::make('reference_data.locaties_evenement')
                            ->label(__('municipality/resources/zaak.infolist.tabs.locations.information.locaties_evenement.label'))
                            ->hidden(fn (?string $state) => empty($state)),
                    ])
                    ->columns(1)
                    ->columnSpan(4),
                Fieldset::make(__('municipality/resources/zaak.infolist.tabs.locations.map.label'))
                    ->schema([
                        TextEntry::make('zaakgeometrie_pending')
                            ->hiddenLabel()
                            ->state('De locatie(s) van het evenement worden op de achtergrond verwerkt en zullen later zichtbaar zijn.')
                            ->hidden(fn (Zaak $record) => ! empty($record->openzaak->zaakgeometrie)),
                        MapEntry::make('zaakgeometrie')
                            ->hiddenLabel()
                            ->geojsonData(fn (Zaak $record) => static::transformGeometry($record->openzaak->zaakgeometrie))
                            ->defaultLocation(50.8514, 5.6910)
                            ->extraAttributes(['class' => 'locaties-kaart'])
                            ->hidden(fn (Zaak $record) => empty($record->openzaak->zaakgeometrie)),
                    ])
                    ->columns(1)
                    ->columnSpan(8)
                    ->extraAttributes(function (Zaak $record): array {
                        if (! empty($record->openzaak->zaakgeometrie)) {
                            return [];
                        }

                        $obsKey = "geo_obs_{$record->id}";
                        $flagKey = "geo_tab_refresh_{$record->id}";

                        return [
                            'x-init' => "
                                if (window['{$obsKey}']) window['{$obsKey}'].disconnect();
                                const panel = \$el.closest('[x-show]');
                                if (!panel) return;
                                window['{$obsKey}'] = new MutationObserver(() => {
                                    if (\$el.offsetParent !== null && !window['{$flagKey}']) {
                                        window['{$flagKey}'] = true;
                                        \$wire.\$refresh().finally(() => { window['{$flagKey}'] = false; });
                                    }
                                });
                                window['{$obsKey}'].observe(panel, { attributes: true, attributeFilter: ['style'] });
                            ",
                        ];
                    }),
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
                'Polygon' => 'Buitenlocatie van het evenement',
                'Point' => 'Adres van het evenement',
                default => null,
            };

            $features[] = [
                'type' => 'Feature',
                'properties' => ['title' => $title],
                'geometry' => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
