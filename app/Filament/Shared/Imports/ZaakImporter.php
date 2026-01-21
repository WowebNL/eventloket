<?php

namespace App\Filament\Shared\Imports;

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class ZaakImporter extends Importer
{
    protected static ?string $model = Zaak::class;

    /**
     * Supported date formats for import
     */
    protected static array $dateFormats = [
        'd/m/Y',
        'd-m-Y',
        'd/m/y',
        'd-m-y',
        'Y-m-d',
        'd/n/Y',
        'd-n-Y',
        'd/n/y',
        'd-n-y',
    ];

    /**
     * Parse a date string using multiple supported formats
     */
    protected static function parseDate(?string $date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        foreach (self::$dateFormats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed !== null && $parsed->year >= 1000) {
                    return $parsed->startOfDay();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    public static function getColumns(): array
    {
        $dateFormatsRule = 'date_format:'.implode(',', self::$dateFormats);

        return [
            ImportColumn::make('submission_date')
                ->label('Aanvraagdatum')
                ->requiredMapping()
                ->rules(['required', 'max:255', $dateFormatsRule]),

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
                ->rules(['required', 'max:255', $dateFormatsRule]),
            ImportColumn::make('end_date')
                ->label('Einddatum')
                ->requiredMapping()
                ->rules(['required', 'max:255', $dateFormatsRule]),

            ImportColumn::make('municipality_code')
                ->label('Gemeentecode')
                ->requiredMapping()
                ->helperText('start altijd met GM en moet bestaan in de applicatie')
                ->rules(['required', 'exists:municipalities,brk_identification']),

            ImportColumn::make('status')
                ->label('Status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('type')
                ->label('Type')
                ->requiredMapping()
                ->helperText('vooraankondiging, melding of vergunning')
                ->rules(['required', 'in:vooraankondiging,melding,vergunning']),
        ];
    }

    public function resolveRecord(): Zaak
    {
        return new Zaak;
    }

    public function fillRecord(): void
    {
        $municipality = Municipality::where('brk_identification', $this->data['municipality_code'])
            ->first();

        if (! $municipality) {
            throw new RowImportFailedException(__('Gemeente met code :code niet gevonden.', ['code' => $this->data['municipality_code']]));
        }

        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        /** @var Zaaktype $zaaktype */
        $zaaktype = $municipality->zaaktypen()
            ->where('name', $operator, '%'.$this->data['type'].'%')
            ->first();

        if (! $zaaktype) {
            throw new RowImportFailedException(__('Geen zaaktype gevonden voor het opgegeven type: :type binnen de gemeente :municipality', ['type' => $this->data['type'], 'municipality' => $municipality->name]));
        }

        $this->record = new Zaak([
            'zaaktype_id' => $zaaktype->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: self::parseDate($this->data['start_date']),
                eind_evenement: self::parseDate($this->data['end_date']),
                registratiedatum: self::parseDate($this->data['submission_date']),
                status_name: $this->data['status'],
                statustype_url: '',
                risico_classificatie: null,
                naam_locatie_eveneme: null,
                naam_evenement: $this->data['event_name'],
                organisator: $this->data['organisation_name'],
                resultaat: null,
                aanwezigen: $this->data['expected_visitors'],
                types_evenement: [$this->data['event_type']],
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
