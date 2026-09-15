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
     * A municipality maps an external zaaktype to at most one role.
     *
     * Call sites that only know a zaaktype resolve its koppeling by municipality
     * plus identificatie and take the first match, so a zaaktype mapped to two
     * roles makes them read the wrong role's statustypen, eigenschappen and
     * documenttypen, and makes the role of the zaaktype itself ambiguous. The
     * form already rejects a duplicate; this index covers the paths that do not
     * go through the form.
     *
     * A null identificatie stays allowed and may repeat: both MySQL and
     * PostgreSQL exclude nulls from a unique index, so a role can still be
     * created before its zaaktype is chosen.
     */
    public function up(): void
    {
        $this->guardAgainstDuplicates();

        $plainIndex = $this->indexOnMappedColumns(unique: false);

        Schema::table(self::TABLE, function (Blueprint $table) use ($plainIndex): void {
            // The plain index on the same two columns becomes redundant: the
            // unique index serves exactly the same lookups. Drop it by the name
            // the database actually carries instead of by the name this table is
            // created with today, because the create migration only started
            // naming this index explicitly later. A database created before that
            // has the generated name, which PostgreSQL silently truncates to its
            // 63-character identifier limit, and dropping a name that is not
            // there aborts the whole migration.
            if ($plainIndex !== null) {
                $table->dropIndex($plainIndex);
            }

            $table->unique(self::COLUMNS, self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        // The mirror image: look the unique index up by its columns as well, so
        // a rollback does not depend on this migration having created it under
        // exactly this name either. The plain index comes back under the
        // explicit name the create migration uses today.
        $uniqueIndex = $this->indexOnMappedColumns(unique: true);

        Schema::table(self::TABLE, function (Blueprint $table) use ($uniqueIndex): void {
            if ($uniqueIndex !== null) {
                // dropUnique, not dropIndex: on PostgreSQL a unique index added
                // through the schema builder is backed by a constraint, which
                // refuses to let the index be dropped out from under it.
                $table->dropUnique($uniqueIndex);
            }

            $table->index(self::COLUMNS, self::PLAIN_INDEX);
        });
    }

    /**
     * The name of the index on exactly the mapped columns, or null when there is
     * none. The column order inside an index does not matter for this lookup, so
     * both sides are sorted before they are compared.
     */
    private function indexOnMappedColumns(bool $unique): ?string
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            $columns = $index['columns'];
            sort($columns);

            if ($columns === self::COLUMNS && $index['unique'] === $unique) {
                return $index['name'];
            }
        }

        return null;
    }

    /**
     * Refuse to add the index while duplicates exist, naming every offending
     * combination and the roles it is mapped to. A bare constraint violation
     * would only report that the index cannot be created and leave whoever runs
     * the migration to hunt for the rows. Which role survives is a configuration
     * choice, so nothing is removed automatically.
     */
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
            ->map(function (object $duplicate): string {
                $roles = DB::table(self::TABLE)
                    ->where('municipality_id', $duplicate->municipality_id)
                    ->where('zaaktype_identificatie', $duplicate->zaaktype_identificatie)
                    ->orderBy('role')
                    ->pluck('role')
                    ->implode(', ');

                return sprintf(
                    '- municipality_id %s, zaaktype_identificatie "%s" is mapped to: %s',
                    $duplicate->municipality_id,
                    $duplicate->zaaktype_identificatie,
                    $roles,
                );
            })
            ->implode(PHP_EOL);

        throw new RuntimeException(
            'Cannot make (municipality_id, zaaktype_identificatie) unique on '
            .'municipality_zaaktype_mappings: the same zaaktype is mapped to more than one '
            .'role. Keep one koppeling per zaaktype, remove or repoint the others, and run '
            .'the migration again.'.PHP_EOL.$lines
        );
    }
};
