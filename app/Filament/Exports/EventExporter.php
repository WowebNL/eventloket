<?php

namespace App\Filament\Exports;

use App\Models\Event;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class EventExporter extends Exporter
{
    protected static ?string $model = Event::class;

    public static function getColumns(): array
    {
        return [
            //            ExportColumn::make('public_id'),
            //            ExportColumn::make('zaaktype.name'),
            //            ExportColumn::make('organisation.name'),
            //            ExportColumn::make('reference_data.risico_classificatie'),
            ExportColumn::make('reference_data.naam_evenement')
                ->label('Naam evenement'),
            ExportColumn::make('reference_data.naam_locatie_eveneme')
                ->label('Locatie'),
            ExportColumn::make('reference_data.start_evenement')
                ->label('Start evenement'),
            ExportColumn::make('reference_data.eind_evenement')
                ->label('Eind evenement'),
            ExportColumn::make('reference_data.organisator')
                ->label('Organisator'),
            ExportColumn::make('reference_data.registratiedatum')
                ->label('Registratiedatum'),
            ExportColumn::make('reference_data.status_name')
                ->label('Status'),
            //            ExportColumn::make('created_at'),
            //            ExportColumn::make('updated_at'),
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
