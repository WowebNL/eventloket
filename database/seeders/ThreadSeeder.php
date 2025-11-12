<?php

namespace Database\Seeders;

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Zaak;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ThreadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zaken = Zaak::with(['organisation.users', 'municipality'])->get();
        $advisories = Advisory::with('users')->get();

        foreach ($zaken as $zaak) {
            // Create 2-4 AdviceThreads per zaak
            $numberOfAdviceThreads = rand(2, 4);

            for ($i = 0; $i < $numberOfAdviceThreads; $i++) {
                $advisory = $advisories->random();
                $municipalityReviewer = $zaak->municipality->reviewerUsers()->inRandomOrder()->first();

                if (! $municipalityReviewer) {
                    continue;
                }

                $adviceThread = AdviceThread::create([
                    'zaak_id' => $zaak->id,
                    'type' => ThreadType::Advice,
                    'title' => $this->getRandomAdviceTitle(),
                    'created_by' => $municipalityReviewer?->id,
                    'advisory_id' => $advisory->id,
                    'advice_status' => $this->getRandomAdviceStatus(),
                    'advice_due_at' => Carbon::now()->addDays(rand(3, 14)),
                ]);

                // Assign to advisor(s) or leave unassigned (50% chance)
                if (rand(0, 1) === 1 && $advisory->users->count() > 0) {
                    $numberOfAdvisors = rand(1, min(2, $advisory->users->count()));
                    $assignedAdvisors = $advisory->users->random($numberOfAdvisors);

                    foreach ($assignedAdvisors as $advisor) {
                        $adviceThread->assignedUsers()->attach($advisor->id);
                    }
                }
            }

            // Create 1-3 OrganiserThreads per zaak
            $numberOfOrganiserThreads = rand(1, 3);

            for ($i = 0; $i < $numberOfOrganiserThreads; $i++) {
                $municipalityReviewer = fake()->boolean() ? $zaak->municipality->reviewerUsers()->inRandomOrder()->first() : $zaak->organisation->users()->inRandomOrder()->first();

                if (! $municipalityReviewer) {
                    continue;
                }

                OrganiserThread::create([
                    'zaak_id' => $zaak->id,
                    'type' => ThreadType::Organiser,
                    'title' => $this->getRandomOrganiserTitle(),
                    'created_by' => $municipalityReviewer?->id,
                ]);
            }
        }
    }

    private function getRandomAdviceTitle(): string
    {
        $titles = [
            'Advies nodig voor veiligheidsplan',
            'Beoordeling verkeersmaatregelen',
            'Controle geluidsnormen evenement',
            'Goedkeuring nooduitgangenplan',
            'Beoordeling catering en horeca',
            'Controle EHBO voorzieningen',
            'Advies brandveiligheid',
            'Beoordeling parkeervoorzieningen',
            'Controle afvalverwerking',
            'Goedkeuring beveiligingsplan',
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomOrganiserTitle(): string
    {
        $titles = [
            'Vraag over vergunningsvoorwaarden',
            'Aanvullende documenten nodig',
            'Verduidelijking evenementopzet',
            'Vraag over locatie-indeling',
            'Informatie over parkeerregeling',
            'Verduidelijking tijdschema',
            'Vraag over geluidsnormen',
            'Aanvullende informatie bezoekers',
            'Verduidelijking catering',
            'Vraag over nooduitgangen',
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomAdviceStatus(): AdviceStatus
    {
        $statuses = [
            AdviceStatus::Asked,
            AdviceStatus::InProgress,
            AdviceStatus::AdvisoryReplied,
            AdviceStatus::Approved,
            AdviceStatus::NeedsMoreInfo,
            AdviceStatus::ApprovedWithConditions,
        ];

        return $statuses[array_rand($statuses)];
    }
}
