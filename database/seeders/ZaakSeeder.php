<?php

namespace Database\Seeders;

use App\Jobs\Zaak\CreateZaak;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaaktype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Woweb\Openzaak\Openzaak;

class ZaakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $municipalities = Municipality::all();
        $organisations = Organisation::all();

        // Good event names for variety
        $eventNames = [
            'Koningsdag Festival',
            'Zomerconcert in het Park',
            'Kerstmarkt Centrum',
            'Straattheater Festival',
            'Lokale Boerenmarkt',
            'Kunstexpositie Stadhuis',
            'Sportevenement Atletiekbaan',
            'Culinair Festival',
            'Muziekfestival Openlucht',
            'Culturele Avond Museum',
            'Kinderfestijn Speeltuin',
            'Literatuurcafé Bibliotheek',
            'Dansvoorstelling Theater',
            'Filmfestival Bioscoop',
            'Wetenschapsdag School',
        ];

        $statuses = ['Ontvangen', 'In behandeling', 'Geregistreerd', 'Afgehandeld'];
        $risicoClassificaties = ['A', 'B', 'C'];

        Artisan::call('app:sync-zaaktypen');

        foreach (Municipality::all() as $municipality) {
            Zaaktype::where('name', 'like', "%{$municipality->name}%")->update(['municipality_id' => $municipality->id, 'is_active' => true]);
        }

        $openzaak = new Openzaak;

        $zaken = $openzaak->zaken()->zaken()->getAll()->take(-25);

        foreach ($zaken as $zaak) {
            CreateZaak::dispatch($zaak['url']);
        }
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
