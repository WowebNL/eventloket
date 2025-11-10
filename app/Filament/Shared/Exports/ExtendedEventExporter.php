<?php

namespace App\Filament\Shared\Exports;

use App\Models\Zaak;
use Filament\Actions\Exports\ExportColumn;

class ExtendedEventExporter extends BaseEventExporter
{
    protected static ?string $model = Zaak::class;

    public static function getColumns(): array
    {
        return array_merge(parent::getColumns(), [
            ExportColumn::make('reference_data.organisator')
                ->label(__('Initiator')),
            ExportColumn::make('organisation.phone')
                ->label(__('resources/zaak.columns.telefoon.label')),
            ExportColumn::make('organiseruser.phone')
                ->label(__('resources/zaak.columns.telefoon-organiser.label')),
            ExportColumn::make('organisation.email')
                ->label(__('resources/zaak.columns.email.label')),
            ExportColumn::make('organiserUser.email')
                ->label(__('resources/zaak.columns.email-organiser.label')),
            ExportColumn::make('reference_data.aanwezigen')
                ->label(__('resources/zaak.columns.aanwezigen.label')),
            ExportColumn::make('reference_data.types_evenement')
                ->label(__('resources/zaak.columns.types_evenement.label')),
            ExportColumn::make('openzaak.resultaattype.omschrijving')
                ->label(__('Resultaat')),
            ExportColumn::make('openzaak.resultaat.toelichting')
                ->label(__('Toelichting op het resultaat')),
        ]);
    }
}
