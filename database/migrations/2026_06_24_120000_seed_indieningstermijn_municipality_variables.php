<?php

use App\Enums\MunicipalityVariableType;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @return list<array{name: string, key: string, type: MunicipalityVariableType, value: mixed}> */
    private function defaults(): array
    {
        return [
            [
                'name' => 'Indieningstermijn risicoclassificatie A (weken)',
                'key' => 'indieningstermijn_a',
                'type' => MunicipalityVariableType::Number,
                'value' => 0,
            ],
            [
                'name' => 'Indieningstermijn risicoclassificatie B (weken)',
                'key' => 'indieningstermijn_b',
                'type' => MunicipalityVariableType::Number,
                'value' => 0,
            ],
            [
                'name' => 'Indieningstermijn risicoclassificatie C (weken)',
                'key' => 'indieningstermijn_c',
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
