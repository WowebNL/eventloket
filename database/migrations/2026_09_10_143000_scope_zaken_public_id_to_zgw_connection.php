<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope the uniqueness of `zaken.public_id` to the ZGW connection the zaak was
 * created on.
 *
 * `public_id` holds the `identificatie` the ZGW instance assigned to the zaak.
 * That value is only unique within the instance that issued it: two instances
 * number their zaken independently, so the same identificatie can legitimately
 * exist on both. The original global unique index therefore rejected the second
 * of two such zaken, even though nothing was wrong with it.
 *
 * The new `zgw_connection` column records which connection issued the number,
 * and the unique index moves to (zgw_connection, public_id). The guarantee
 * narrows, it does not disappear: one number can still only be used once per
 * connection.
 *
 * A plain index on `public_id` is kept alongside it, because the composite
 * unique cannot serve a lookup that only knows the number (operator commands and
 * the vooraankondiging lookup do exactly that) and the old unique index used to.
 *
 * The connection names written here mirror the runtime resolver: the shared
 * connection is "main", and a municipality that runs its own instance is
 * "gemeente_{municipality_id}". They are spelled out literally rather than read
 * from the resolver so this migration keeps describing the schema as it was
 * built, independent of later changes to that class.
 */
return new class extends Migration
{
    /**
     * The shared connection every zaak used before per-municipality connections
     * existed, and the value every row that cannot be attributed falls back to.
     */
    private const DEFAULT_CONNECTION = 'main';

    public function up(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->string('zgw_connection')->default(self::DEFAULT_CONNECTION)->after('public_id');
        });

        $this->backfillConnections();

        Schema::table('zaken', function (Blueprint $table) {
            $table->dropUnique('zaken_public_id_unique');
            $table->unique(['zgw_connection', 'public_id']);
            $table->index('public_id');
        });
    }

    public function down(): void
    {
        $this->assertPublicIdIsGloballyUnique();

        Schema::table('zaken', function (Blueprint $table) {
            $table->dropUnique('zaken_zgw_connection_public_id_unique');
            $table->dropIndex('zaken_public_id_index');
            $table->unique('public_id');
            $table->dropColumn('zgw_connection');
        });
    }

    /**
     * Attribute the existing rows to the connection their zaaktype resolves to.
     *
     * The rule is the one the resolver applies: a zaaktype whose `connection`
     * column says "main" lives on the shared connection, and any other value
     * resolves through its municipality, provided that municipality has an
     * activated connection of its own. Everything else keeps the column default,
     * so a deployment without per-municipality connections needs no data change
     * at all.
     *
     * Written as one statement per own-instance municipality rather than a
     * correlated update, so it behaves the same on PostgreSQL and MySQL.
     *
     * Public so the attribution rule can be exercised on a dataset with several
     * connections without running the schema change around it.
     */
    public function backfillConnections(): void
    {
        $municipalityIds = DB::table('municipality_zgw_connections')
            ->whereNotNull('activated_at')
            ->orderBy('municipality_id')
            ->pluck('municipality_id');

        foreach ($municipalityIds as $municipalityId) {
            $zaaktypeIds = DB::table('zaaktypen')
                ->where('municipality_id', $municipalityId)
                ->where('connection', '!=', self::DEFAULT_CONNECTION)
                ->pluck('id');

            if ($zaaktypeIds->isEmpty()) {
                continue;
            }

            DB::table('zaken')
                ->whereIn('zaaktype_id', $zaaktypeIds)
                ->update(['zgw_connection' => 'gemeente_'.$municipalityId]);
        }
    }

    /**
     * Refuse to roll back once the data depends on the wider key.
     *
     * Going back to a global unique index is only possible while no number is
     * held by more than one row. From the moment two connections have both used
     * the same identificatie, restoring the old index means deciding which of
     * the two zaken to give up, and that is a data decision no migration should
     * make on its own. Failing here with the numbers in hand is the honest
     * outcome; the rows have to be reconciled first.
     *
     * Public for the same reason as {@see backfillConnections()}: the refusal is
     * the interesting behaviour and it is checkable on its own.
     */
    public function assertPublicIdIsGloballyUnique(): void
    {
        $duplicates = DB::table('zaken')
            ->select('public_id')
            ->whereNotNull('public_id')
            ->groupBy('public_id')
            ->havingRaw('count(*) > 1')
            ->pluck('public_id');

        if ($duplicates->isEmpty()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot restore the global unique index on zaken.public_id: %d number(s) are in use on more than one connection (%s%s). Reconcile those rows first.',
            $duplicates->count(),
            $duplicates->take(5)->implode(', '),
            $duplicates->count() > 5 ? ', ...' : '',
        ));
    }
};
