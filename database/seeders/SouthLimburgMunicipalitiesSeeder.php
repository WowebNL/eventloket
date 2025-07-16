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
            'Brunssum',
            'Eijsden-Margraten',
            'Gulpen-Wittem',
            'Heerlen',
            'Kerkrade',
            'Landgraaf',
            'Maastricht',
            'Meerssen',
            'Nuth',
            'Onderbanken',
            'Schinnen',
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
