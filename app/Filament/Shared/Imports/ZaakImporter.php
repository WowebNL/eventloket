<?php

namespace App\Filament\Shared\Imports;

use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class ZaakImporter extends Importer
{
    protected static ?string $model = Zaak::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('submission_date')
                ->label('Aanvraagdatum')
                ->requiredMapping()
                ->rules(['required', 'max:255', 'date']),

            ImportColumn::make('contact_first_name')
                ->label('Voornaam contactpersoon')
                ->guess(['Voornaam contactpersoon'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('contact_last_name')
                ->label('Achternaam contactpersoon')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('contact_phone')
                ->label('Telefoonnummer contactpersoon')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('contact_email')
                ->label('E-mail contactpersoon')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('organisation_name')
                ->label('Naam organisatie')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('organisation_street')
                ->label('Straat organisatie')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('organisation_postal_code')
                ->label('Postcode organisatie')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('organisation_house_number')
                ->label('Huisnummer organisatie')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('event_name')
                ->label('Naam evenement')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_type')
                ->label('Soort evenement')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_street')
                ->label('Straat evenement')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_postal_code')
                ->label('Postcode evenement')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('expected_visitors')
                ->label('Aantal verwachte aanwezigen')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('start_date')
                ->label('Startdatum')
                ->requiredMapping()
                ->rules(['required', 'max:255', Rule::date()->format('d/m/y')]),
            ImportColumn::make('end_date')
                ->label('Einddatum')
                ->requiredMapping()
                ->rules(['required', 'max:255', Rule::date()->format('d/m/y')]),

            ImportColumn::make('municipality_code')
                ->label('Gemeentecode')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('status')
                ->label('Status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('type')
                ->label('Type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
        ];
    }

    public function resolveRecord(): Zaak
    {
        return new Zaak;
    }

    public function fillRecord(): void
    {
        $this->record = new Zaak([
            'reference_data' => new ZaakReferenceData(
                start_evenement: Carbon::createFromFormat('d/m/y', $this->data['start_date'])->startOfDay(),
                eind_evenement: Carbon::createFromFormat('d/m/y', $this->data['end_date'])->startOfDay(),
                registratiedatum: $this->data['submission_date'],
                status_name: $this->data['status'],
                statustype_url: '',
                risico_classificatie: null,
                naam_locatie_eveneme: null,
                naam_evenement: $this->data['event_name'],
                organisator: $this->data['organisation_name'],
                resultaat: null,
                aanwezigen: $this->data['expected_visitors'],
                types_evenement: [$this->data['type']],
            ),
            'imported_data' => $this->data,
        ]);
    }

    public function saveRecord(): void
    {
        Model::withoutEvents(function () {
            parent::saveRecord();
        });
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('shared/widgets/calendar.actions.import.completed_notification.body', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= __('shared/widgets/calendar.actions.import.completed_notification.failed', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
