<?php

declare(strict_types=1);

namespace App\Console\Commands\Zaak;

use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for reference data whose date fields hold the ZGW wire form
 * (YYYYMMDDHHMMSS or YYYYMMDD) instead of ISO 8601.
 *
 * The reference data sync used to merge zaakeigenschap values back verbatim,
 * so a zaak refreshed after its eigenschappen were written could end up with
 * its event dates in the wire form. Those zaken drop out of every query that
 * compares the dates as ISO 8601 text. This command converts such values with
 * the same read-back rules the sync now applies
 * ({@see ZaakReferenceData::normalizeEigenschapDates()}).
 *
 * It only counts by default. Writing needs --force. It is idempotent: a
 * converted value is ISO 8601 and is not touched again. The output holds
 * counts only, never a zaak or any of its data.
 */
class NormalizeReferenceDates extends Command
{
    protected $signature = 'zaak:normalize-reference-dates
        {--dry-run : Only count the values that would be converted (the default)}
        {--force : Write the converted values}';

    protected $description = 'Convert reference data dates stored in the ZGW wire format back to ISO 8601.';

    public function handle(): int
    {
        if ($this->option('force') && $this->option('dry-run')) {
            $this->error('Pass either --dry-run or --force, not both.');

            return self::FAILURE;
        }

        $write = (bool) $this->option('force');

        $scanned = 0;
        $affected = 0;
        $perField = array_fill_keys(ZaakReferenceData::DATE_TIME_FIELDS, 0);

        // The query builder, not the model: trashed zaken are included, no
        // model events fire, and updated_at keeps saying when the zaak itself
        // last changed.
        DB::table('zaken')
            ->select(['id', 'reference_data'])
            ->whereNotNull('reference_data')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($write, &$scanned, &$affected, &$perField): void {
                foreach ($rows as $row) {
                    $scanned++;

                    $changed = $this->changedFields($row->reference_data);

                    if ($changed === []) {
                        continue;
                    }

                    $affected++;
                    foreach ($changed as $field) {
                        $perField[$field]++;
                    }

                    if ($write) {
                        $this->convert($row->id);
                    }
                }
            });

        $this->info(sprintf('Scanned %d zaak/zaken; %d with a date in the ZGW wire format.', $scanned, $affected));
        $this->table(
            ['Field', 'Values'],
            collect($perField)->map(fn (int $count, string $field): array => [$field, $count])->values()->all(),
        );

        if ($affected === 0) {
            $this->info('Nothing to convert.');
        } elseif ($write) {
            $this->info(sprintf('Converted the dates of %d zaak/zaken to ISO 8601.', $affected));
        } else {
            $this->warn('Dry run: nothing was written. Run again with --force to convert these values.');
        }

        return self::SUCCESS;
    }

    /**
     * The date fields of one stored reference data row that are in the wire form.
     *
     * @return list<string>
     */
    private function changedFields(mixed $raw): array
    {
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data)) {
            return [];
        }

        $normalized = ZaakReferenceData::normalizeEigenschapDates($data);

        return array_values(array_filter(
            ZaakReferenceData::DATE_TIME_FIELDS,
            fn (string $field): bool => ($data[$field] ?? null) !== ($normalized[$field] ?? null),
        ));
    }

    /**
     * Re-read the row under a lock and convert it, so a sync that wrote the
     * row in the meantime is not overwritten with an older copy.
     */
    private function convert(int|string $id): void
    {
        DB::transaction(function () use ($id): void {
            $raw = DB::table('zaken')->where('id', $id)->lockForUpdate()->value('reference_data');
            $data = is_string($raw) ? json_decode($raw, true) : null;

            if (! is_array($data)) {
                return;
            }

            $normalized = ZaakReferenceData::normalizeEigenschapDates($data);

            if ($normalized !== $data) {
                DB::table('zaken')->where('id', $id)->update(['reference_data' => json_encode($normalized)]);
            }
        });
    }
}
