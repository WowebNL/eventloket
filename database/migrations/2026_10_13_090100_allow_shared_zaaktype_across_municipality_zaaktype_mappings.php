<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'municipality_zaaktype_mappings';

    /** @var list<string> */
    private const COLUMNS = ['municipality_id', 'zaaktype_identificatie'];

    private const PLAIN_INDEX = 'municipality_zaaktype_map_identificatie_index';

    private const UNIQUE_INDEX = 'municipality_zaaktype_map_identificatie_unique';

    /**
     * Let a municipality map several roles to the same zaaktype.
     *
     * The unique index only existed because the role of a zaak was derived from
     * its zaaktype. Zaken now carry their own role (backfilled by the previous
     * migration while this index still guaranteed one koppeling per zaaktype),
     * and every lookup from a zaak goes through that role, so the restriction
     * is no longer needed. The plain index comes back for the lookups by
     * municipality and identificatie.
     */
    public function up(): void
    {
        $uniqueIndex = $this->indexOnMappedColumns(unique: true);

        Schema::table(self::TABLE, function (Blueprint $table) use ($uniqueIndex): void {
            if ($uniqueIndex !== null) {
                // dropUnique, not dropIndex: on PostgreSQL a unique index added
                // through the schema builder is backed by a constraint.
                $table->dropUnique($uniqueIndex);
            }

            if ($this->indexOnMappedColumns(unique: false) === null) {
                $table->index(self::COLUMNS, self::PLAIN_INDEX);
            }
        });
    }

    /**
     * Restore the unique index, refusing while a zaaktype is mapped to more
     * than one role. Which role keeps the zaaktype is a configuration choice,
     * so nothing is removed automatically.
     */
    public function down(): void
    {
        $this->guardAgainstDuplicates();

        $plainIndex = $this->indexOnMappedColumns(unique: false);

        Schema::table(self::TABLE, function (Blueprint $table) use ($plainIndex): void {
            if ($plainIndex !== null) {
                $table->dropIndex($plainIndex);
            }

            $table->unique(self::COLUMNS, self::UNIQUE_INDEX);
        });
    }

    /**
     * The name of the index on exactly the mapped columns, or null when there is
     * none. The column order inside an index does not matter for this lookup.
     */
    private function indexOnMappedColumns(bool $unique): ?string
    {
        $expected = self::COLUMNS;
        sort($expected);

        foreach (Schema::getIndexes(self::TABLE) as $index) {
            $columns = $index['columns'];
            sort($columns);

            if ($columns === $expected && $index['unique'] === $unique) {
                return $index['name'];
            }
        }

        return null;
    }

    private function guardAgainstDuplicates(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('municipality_id', 'zaaktype_identificatie')
            ->whereNotNull('zaaktype_identificatie')
            ->groupBy('municipality_id', 'zaaktype_identificatie')
            ->havingRaw('count(*) > 1')
            ->orderBy('municipality_id')
            ->orderBy('zaaktype_identificatie')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $lines = $duplicates
            ->map(fn (object $duplicate): string => sprintf(
                '- municipality_id %s, zaaktype_identificatie "%s" is mapped to: %s',
                $duplicate->municipality_id,
                $duplicate->zaaktype_identificatie,
                DB::table(self::TABLE)
                    ->where('municipality_id', $duplicate->municipality_id)
                    ->where('zaaktype_identificatie', $duplicate->zaaktype_identificatie)
                    ->orderBy('role')
                    ->pluck('role')
                    ->implode(', '),
            ))
            ->implode(PHP_EOL);

        throw new RuntimeException(
            'Cannot roll back: the same zaaktype is mapped to more than one role on '
            .'municipality_zaaktype_mappings. Keep one koppeling per zaaktype, remove or '
            .'repoint the others, and roll back again.'.PHP_EOL.$lines
        );
    }
};
