<?php

use App\Enums\MunicipalityVariableType;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use Illuminate\Database\Migrations\Migration;

/**
 * Mirror of the migration that seeded the per-classification submission
 * deadlines. The report path has no risk classification, so it needs a
 * deadline of its own; same variable type, same zero default, same
 * is_default flag, so a municipality configures it in the same place and
 * in the same way as the other three.
 *
 * It mirrors that migration's rollback characteristics as well. The model
 * soft-deletes, so `down()` marks the row as deleted rather than removing
 * it, and the variables are read back with trashed rows included. Running
 * `down()` and `up()` in sequence therefore adds a second row next to the
 * first, because the unique index only covers rows that are not deleted.
 * The same holds for the three deadlines seeded earlier; changing it is a
 * separate piece of work that has to cover all four at once.
 */
return new class extends Migration
{
    /** @return list<array{name: string, key: string, type: MunicipalityVariableType, value: mixed}> */
    private function defaults(): array
    {
        return [
            [
                'name' => 'Indieningstermijn melding (weken)',
                'key' => 'indieningstermijn_melding',
                'type' => MunicipalityVariableType::Number,
                'value' => 0,
            ],
        ];
    }

    public function up(): void
    {
        $defaults = $this->defaults();

        Municipality::all()->each(function (Municipality $municipality) use ($defaults): void {
            foreach ($defaults as $entry) {
                MunicipalityVariable::firstOrCreate(
                    ['municipality_id' => $municipality->id, 'key' => $entry['key']],
                    [
                        'name' => $entry['name'],
                        'type' => $entry['type'],
                        'value' => $entry['value'],
                        'is_default' => true,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        $keys = array_map(fn (array $d): string => $d['key'], $this->defaults());

        MunicipalityVariable::whereIn('key', $keys)
            ->where('is_default', true)
            ->delete();
    }
};
