<?php

namespace Database\Seeders;

use App\Models\Advisory;
use Illuminate\Database\Seeder;

class AdvisorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $advisories = [
            'Brandweer',
            'Politie',
            'Milieudienst',
            'Verkeerscoördinatie',
            'Evenementencoördinator',
        ];

        foreach ($advisories as $name) {
            Advisory::factory()->create(['name' => $name]);
        }
    }
}
