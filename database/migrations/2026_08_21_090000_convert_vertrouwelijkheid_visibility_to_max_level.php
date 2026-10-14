<?php

use App\Enums\DocumentVertrouwelijkheden;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Converts the per-role entries of `vertrouwelijkheid_map.visibility` from a
     * set of visible levels to the single maximum level that now describes them.
     *
     * A maximum vertrouwelijkheidaanduiding is inclusive over the ordered scale
     * (openbaar through zeer_geheim), so one level per role group expresses the
     * same thing a set did, without allowing gaps a role-based filter cannot
     * honour. A stored set is converted to its most confidential member: that is
     * the level it granted access up to, and every less confidential level it
     * listed is covered by the maximum anyway.
     *
     * Entries that are already a single level, and levels outside the standard,
     * are left untouched, so the migration is safe to run more than once.
     */
    public function up(): void
    {
        $this->convert(static function (mixed $stored): mixed {
            if (! is_array($stored)) {
                return $stored;
            }

            return DocumentVertrouwelijkheden::mostConfidential($stored);
        });
    }

    /**
     * Expands each maximum back into the set of levels it covers. That restores
     * a working map rather than the literal previous value: a set with gaps was
     * never expressible as a maximum, and the maximum covers every level below it
     * by definition.
     */
    public function down(): void
    {
        $this->convert(static function (mixed $stored): mixed {
            if (! is_string($stored)) {
                return $stored;
            }

            $levels = DocumentVertrouwelijkheden::atMost($stored);

            return $levels === [] ? $stored : $levels;
        });
    }

    /**
     * Rewrite every connection's visibility entries with the given converter,
     * writing back only when something actually changed.
     */
    private function convert(callable $converter): void
    {
        DB::table('municipality_zgw_connections')
            ->whereNotNull('vertrouwelijkheid_map')
            ->orderBy('id')
            ->each(function (object $connection) use ($converter): void {
                $map = json_decode((string) $connection->vertrouwelijkheid_map, true);

                if (! is_array($map) || ! isset($map['visibility']) || ! is_array($map['visibility'])) {
                    return;
                }

                $original = $map['visibility'];
                $converted = [];

                foreach ($original as $role => $stored) {
                    $value = $converter($stored);

                    // A set holding no level of the standard says nothing; drop
                    // the entry so the role falls back to the defaults.
                    if ($value !== null) {
                        $converted[$role] = $value;
                    }
                }

                if ($converted === $original) {
                    return;
                }

                if ($converted === []) {
                    unset($map['visibility']);
                } else {
                    $map['visibility'] = $converted;
                }

                DB::table('municipality_zgw_connections')
                    ->where('id', $connection->id)
                    ->update(['vertrouwelijkheid_map' => $map === [] ? null : json_encode($map)]);
            });
    }
};
