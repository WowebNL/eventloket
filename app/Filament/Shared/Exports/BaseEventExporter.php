<?php

namespace App\Filament\Shared\Exports;

use App\Models\Event;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class BaseEventExporter extends Exporter
{
    protected static ?string $model = Event::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_data.naam_evenement')
                ->label(__('resources/zaak.columns.naam_evenement.label')),
            ExportColumn::make('public_id')
                ->label(__('resources/zaak.columns.public_id.label')),
            ExportColumn::make('zaaktype.name')
                ->label(__('resources/zaak.columns.zaaktype.label')),
            ExportColumn::make('reference_data.risico_classificatie')
                ->label(__('resources/zaak.columns.risico_classificatie.label')),
            ExportColumn::make('municipality.name')
                ->label(__('Ingediend bij gemeente')),
            ExportColumn::make('reference_data.naam_locatie_evenement')
                ->label(__('municipality/resources/zaak.infolist.tabs.locations.information.location_name.label')),
            ExportColumn::make('openzaak.zaakAddresses')
                ->label(__('municipality/resources/zaak.infolist.tabs.locations.information.address.label')),
            ExportColumn::make('reference_data.start_evenement_datetime')
                ->label(__('resources/zaak.columns.start_evenement.label'))
                ->formatStateUsing(fn ($state) => $state?->format(config('app.datetime_format'))),
            ExportColumn::make('reference_data.eind_evenement_datetime')
                ->label(__('resources/zaak.columns.eind_evenement.label'))
                ->formatStateUsing(fn ($state) => $state?->format(config('app.datetime_format'))),
            ExportColumn::make('organisation.name')
                ->label(__('municipality/resources/zaak.columns.organisator.label')),
            ExportColumn::make('reference_data.registratiedatum_datetime')
                ->label(__('resources/zaak.columns.registratiedatum.label'))
                ->formatStateUsing(fn ($state) => $state?->format(config('app.date_format'))),
            ExportColumn::make('reference_data.status_name')
                ->label(__('resources/zaak.columns.status.label')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Je evenementen export is afgerond en '.Number::format($export->successful_rows).' '.str('rij')->plural($export->successful_rows).' zijn geëxporteerd.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('rij')->plural($failedRowsCount).' konden niet worden geëxporteerd.';
        }

        return $body;
    }
}
