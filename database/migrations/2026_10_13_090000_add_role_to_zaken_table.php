<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The role names and zaaktype-name prefixes as they stand when this
     * migration is written. Copied rather than read from the enum, so a later
     * change to the enum cannot change what this backfill did.
     *
     * @var array<string, string>
     */
    private const NAME_PREFIXES = [
        'vergunning' => 'evenementenvergunning',
        'melding' => 'melding',
        'vooraankondiging' => 'vooraankondiging',
        'doorkomst' => 'doorkomst',
    ];

    /**
     * Store the role of a zaak on the zaak itself.
     *
     * Until now the role was derived from the zaaktype, which only works while
     * every zaaktype serves a single role. Recording it per zaak lets several
     * roles share one zaaktype.
     *
     * Existing zaken are backfilled with the same ladder the zaaktype uses to
     * derive its role. This has to run while the (municipality_id,
     * zaaktype_identificatie) index on the koppelingen is still unique: only
     * then does a zaaktype resolve to exactly one koppeling, so the role read
     * from it is unambiguous. The migration that drops that index is dated
     * after this one for that reason.
     */
    public function up(): void
    {
        Schema::table('zaken', function (Blueprint $table): void {
            $table->string('role')->nullable()->after('zaaktype_id');
            $table->index('role');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    private function backfill(): void
    {
        // 1. A deelzaak is always created for a route passage.
        DB::table('zaken')
            ->whereNull('role')
            ->whereNotNull('hoofdzaak_id')
            ->update(['role' => 'doorkomst']);

        // 2. The koppeling of the municipality for this zaaktype.
        DB::table('zaken')
            ->whereNull('role')
            ->whereExists(fn (Builder $sub) => $this->koppelingOfZaak($sub->select(DB::raw(1))))
            ->update(['role' => DB::raw('('.$this->koppelingOfZaak(DB::query()->select('municipality_zaaktype_mappings.role'))->limit(1)->toRawSql().')')]);

        // 3. The role column of the zaaktype, for zaaktypen without a koppeling.
        DB::table('zaken')
            ->whereNull('role')
            ->whereExists(fn (Builder $sub) => $this->zaaktypeOfZaak($sub->select(DB::raw(1)))->whereNotNull('zaaktypen.role'))
            ->update(['role' => DB::raw('('.$this->zaaktypeOfZaak(DB::query()->select('zaaktypen.role'))->limit(1)->toRawSql().')')]);

        // 4. The naming convention of the shared catalogus, for zaaktypen with
        //    neither a koppeling nor a role. Lowered on both sides, like the
        //    model does, so PostgreSQL and MySQL answer the same.
        foreach (self::NAME_PREFIXES as $role => $prefix) {
            DB::table('zaken')
                ->whereNull('role')
                ->whereExists(fn (Builder $sub) => $this->zaaktypeOfZaak($sub->select(DB::raw(1)))
                    ->whereNull('zaaktypen.role')
                    ->whereRaw('lower(zaaktypen.name) like ?', [$prefix.'%']))
                ->update(['role' => $role]);
        }

        $unresolved = DB::table('zaken')->whereNull('role')->count();

        if ($unresolved > 0) {
            // These zaken keep deriving their role from the zaaktype at runtime.
            Log::info('Backfill of zaken.role left zaken without a role', ['count' => $unresolved]);
        }
    }

    /**
     * The koppeling of the zaaktype of the zaak in the outer query.
     */
    private function koppelingOfZaak(Builder $query): Builder
    {
        return $query
            ->from('municipality_zaaktype_mappings')
            ->join('zaaktypen', function ($join): void {
                $join->on('zaaktypen.municipality_id', '=', 'municipality_zaaktype_mappings.municipality_id')
                    ->on('zaaktypen.identificatie', '=', 'municipality_zaaktype_mappings.zaaktype_identificatie');
            })
            ->whereColumn('zaaktypen.id', 'zaken.zaaktype_id');
    }

    /**
     * The zaaktype of the zaak in the outer query.
     */
    private function zaaktypeOfZaak(Builder $query): Builder
    {
        return $query
            ->from('zaaktypen')
            ->whereColumn('zaaktypen.id', 'zaken.zaaktype_id');
    }
};
