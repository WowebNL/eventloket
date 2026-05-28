<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ZaakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Artisan::call('app:sync-zaaktypen');

        foreach (Municipality::all() as $municipality) {
            Zaaktype::where('name', 'like', "%{$municipality->name}%")->update(['municipality_id' => $municipality->id, 'is_active' => true]);
        }

        // Seeden van fake `Zaak`-rijen gebeurde voorheen door OF-zaken uit
        // OpenZaak te trekken en via `CreateZaak::dispatch` lokale kopieën
        // te maken. Die job (en flow) zijn er niet meer; als we fake zaken
        // nodig hebben voor lokale dev, bouwen we dat apart op via een
        // Factory (Zaak::factory()->create(...)).
    }

    private function getRandomLocation(): string
    {
        $locations = [
            'Marktplein',
            'Stadspark',
            'Sporthal de Eenheid',
            'Dorpshuis',
            'Gemeentehuis',
            'Muziekcentrum',
            'Cultureel Centrum',
            'Openluchttheater',
            'Sportcomplex',
            'Bibliotheek',
            'Speeltuin Centrum',
            'Kerkplein',
            'School de Regenboog',
            'Tennispark',
            'Zwembad',
        ];

        return $locations[array_rand($locations)];
    }

    private function getRandomEventTypes(): array
    {
        $allTypes = [
            'Muziek',
            'Sport',
            'Cultuur',
            'Markt',
            'Festival',
            'Theater',
            'Dans',
            'Kinderen',
            'Food & Drinks',
            'Kunst',
            'Literatuur',
            'Film',
            'Wetenschap',
        ];

        // Return 1-3 random event types
        $numberOfTypes = rand(1, 3);
        $selectedTypes = array_rand($allTypes, $numberOfTypes);

        if (is_array($selectedTypes)) {
            return array_map(fn ($key) => $allTypes[$key], $selectedTypes);
        } else {
            return [$allTypes[$selectedTypes]];
        }
    }
}
