<?php

use App\Models\StatusResultaatColor;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @return list<array{status_name: string, resultaat: ?string, color: string}>
     */
    private function defaults(): array
    {
        return [
            ['status_name' => 'Ontvangen', 'resultaat' => null, 'color' => '#3B82F6'],
            ['status_name' => 'In behandeling', 'resultaat' => null, 'color' => '#F59E0B'],
            ['status_name' => 'Afgerond', 'resultaat' => null, 'color' => '#22C55E'],
            ['status_name' => 'Afgerond', 'resultaat' => 'Verleend', 'color' => '#22C55E'],
            ['status_name' => 'Afgerond', 'resultaat' => 'Geweigerd', 'color' => '#EF4444'],
            ['status_name' => 'Afgerond', 'resultaat' => 'Ingetrokken', 'color' => '#6B7280'],
            ['status_name' => 'Afgerond', 'resultaat' => 'Afgebroken', 'color' => '#6B7280'],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->defaults() as $entry) {
            StatusResultaatColor::firstOrCreate(
                ['status_name' => $entry['status_name'], 'resultaat' => $entry['resultaat']],
                ['color' => $entry['color']],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->defaults() as $entry) {
            StatusResultaatColor::where('status_name', $entry['status_name'])
                ->where('resultaat', $entry['resultaat'])
                ->delete();
        }
    }
};
