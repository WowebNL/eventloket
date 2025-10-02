<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas\Components;

use App\Livewire\Zaken\Map;
use App\Models\Zaak;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Str;

class LocationsTab
{
    public static function make(): Tab
    {
        return Tab::make('locations')
            ->label(__('municipality/resources/zaak.infolist.tabs.locations.label'))
            ->icon('heroicon-o-map-pin')
            ->columns(12)
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
                        Livewire::make(Map::class, fn (Zaak $record) => ['geojson' => $record->openzaak->zaakgeometrie])->key('map-'.Str::uuid()),
                    ])
                    ->columns(1)
                    ->columnSpan(8),
            ]);
    }
}
