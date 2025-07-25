<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class SouthLimburgMunicipalitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $municipalities = [
            'Beek',
            'Beekdaelen',
            'Brunssum',
            'Eijsden-Margraten',
            'Gulpen-Wittem',
            'Heerlen',
            'Kerkrade',
            'Landgraaf',
            'Meerssen',
            'Simpelveld',
            'Sittard-Geleen',
            'Stein',
            'Vaals',
            'Valkenburg aan de Geul',
            'Voerendaal',
        ];

        foreach ($municipalities as $name) {
            Municipality::factory()->create(['name' => $name]);
        }
    }
}
