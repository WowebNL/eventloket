<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

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

        foreach ($municipalities as $municipality) {
            // Create a zaaktype for each municipality
            $zaaktype = Zaaktype::factory()->create([
                'municipality_id' => $municipality->id,
                'name' => 'Evenementenvergunning '.$municipality->name,
                'is_active' => true,
            ]);

            // Create 5-8 zaken for each municipality
            $numberOfZaken = rand(5, 8);

            for ($i = 0; $i < $numberOfZaken; $i++) {
                $startDate = Carbon::now()->addDays(rand(-30, 90)); // Events from 30 days ago to 90 days in future
                $endDate = $startDate->copy()->addHours(rand(2, 12)); // Events last 2-12 hours
                $organisation = $organisations->random();

                Zaak::factory()->create([
                    'zaaktype_id' => $zaaktype->id,
                    'organisation_id' => $organisation->id,
                    'reference_data' => new ZaakReferenceData(
                        start_evenement: $startDate->toISOString(),
                        eind_evenement: $endDate->toISOString(),
                        registratiedatum: Carbon::now()->subDays(rand(1, 30))->toISOString(),
                        status_name: $statuses[array_rand($statuses)],
                        risico_classificatie: $risicoClassificaties[array_rand($risicoClassificaties)],
                        naam_locatie_eveneme: $this->getRandomLocation(),
                        naam_evenement: $eventNames[array_rand($eventNames)],
                        organisator: $organisation->name,
                        resultaat: rand(0, 1) ? 'Goedgekeurd' : null,
                        aanwezigen: rand(50, 5000),
                        types_evenement: $this->getRandomEventTypes(),
                    ),
                ]);
            }
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
