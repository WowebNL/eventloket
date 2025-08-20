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
            'GM0888' => 'Beek',
            'GM1954' => 'Beekdaelen',
            'GM0899' => 'Brunssum',
            'GM1903' => 'Eijsden-Margraten',
            'GM1729' => 'Gulpen-Wittem',
            'GM0917' => 'Heerlen',
            'GM0928' => 'Kerkrade',
            'GM0882' => 'Landgraaf',
            'GM0935' => 'Maastricht',
            'GM0938' => 'Meerssen',
            'GM0965' => 'Simpelveld',
            'GM1883' => 'Sittard-Geleen',
            'GM0971' => 'Stein',
            'GM0981' => 'Vaals',
            'GM0994' => 'Valkenburg aan de Geul',
            'GM0986' => 'Voerendaal',
        ];

        foreach ($municipalities as $brk_id => $name) {
            $model = Municipality::updateOrCreate(['name' => $name], ['brk_identification' => $brk_id]);
            dispatch(new \App\Jobs\ProcessSyncGeometryOnMunicipality($model));
        }
    }
}
