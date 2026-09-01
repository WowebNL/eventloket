<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table): void {
            // The plain index on the same two columns becomes redundant: the
            // unique index serves exactly the same lookups.
            $table->dropIndex(self::PLAIN_INDEX);
            $table->unique(['municipality_id', 'zaaktype_identificatie'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table): void {
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->index(['municipality_id', 'zaaktype_identificatie'], self::PLAIN_INDEX);
        });
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
        $duplicates = DB::table('municipality_zaaktype_mappings')
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
                $roles = DB::table('municipality_zaaktype_mappings')
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
