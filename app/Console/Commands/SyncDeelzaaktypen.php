<?php

namespace App\Console\Commands;

use App\Models\Zaaktype;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Woweb\Openzaak\Connection\OpenzaakConnection;

class SyncDeelzaaktypen extends Command
{
    protected $signature = 'app:sync-deelzaaktypen';

    protected $description = 'Koppelt doorkomst zaaktypen als deelzaaktypen aan evenementenvergunning zaaktypen in Open Zaak';

    public function handle(): int
    {
        $this->info('Deelzaaktypen koppelen aan evenementenvergunning zaaktypen...');

        $doorkomstZaaktypen = Zaaktype::where('is_active', true)
            ->where('name', 'like', '%Doorkomst%')
            ->whereNotNull('municipality_id')
            ->whereNotNull('zgw_zaaktype_url')
            ->get();

        if ($doorkomstZaaktypen->isEmpty()) {
            $this->warn('Geen actieve doorkomst zaaktypen gevonden. Voer eerst app:sync-zaaktypen uit.');

            return Command::FAILURE;
        }

        $evenementenZaaktypen = Zaaktype::where('is_active', true)
            ->where('name', 'like', '%Evenementenvergunning%')
            ->whereNotNull('municipality_id')
            ->whereNotNull('zgw_zaaktype_url')
            ->get();

        if ($evenementenZaaktypen->isEmpty()) {
            $this->warn('Geen actieve evenementenvergunning zaaktypen gevonden. Voer eerst app:sync-zaaktypen uit.');

            return Command::FAILURE;
        }

        $this->line("Doorkomst zaaktypen gevonden: {$doorkomstZaaktypen->count()}");
        $this->line("Evenementenvergunning zaaktypen gevonden: {$evenementenZaaktypen->count()}");

        $connection = new OpenzaakConnection;
        $headers = $connection->getHeaders();
        $baseUrl = rtrim((string) config('openzaak.url'), '/').'/catalogi/api/v1/';

        $updated = 0;
        $alreadyCorrect = 0;
        $failed = 0;

        foreach ($evenementenZaaktypen as $evenementenZaaktype) {
            // All doorkomst zaaktypen except the one belonging to the same municipality
            $expectedUrls = $doorkomstZaaktypen
                ->where('municipality_id', '!=', $evenementenZaaktype->municipality_id)
                ->pluck('zgw_zaaktype_url')
                ->values()
                ->toArray();

            sort($expectedUrls);

            // Fetch the current zaaktype state from Open Zaak
            $currentResponse = Http::withHeaders($headers)->get($evenementenZaaktype->zgw_zaaktype_url);

            if (! $currentResponse->successful()) {
                $this->warn("  Ophalen mislukt voor {$evenementenZaaktype->name}: HTTP {$currentResponse->status()}");
                $failed++;

                continue;
            }

            $currentData = $currentResponse->json();
            $currentUrls = $currentData['deelzaaktypen'] ?? [];
            sort($currentUrls);

            if ($currentUrls === $expectedUrls) {
                $this->line("  <comment>Al correct</comment>: {$evenementenZaaktype->name}");
                $alreadyCorrect++;

                continue;
            }

            $count = count($expectedUrls);
            $this->line("  Bijwerken: {$evenementenZaaktype->name} ({$count} deelzaaktypen verwacht)");

            $uuid = basename((string) $evenementenZaaktype->zgw_zaaktype_url);
            $isConcept = (bool) ($currentData['concept'] ?? false);

            // Open Zaak only allows patching structural fields (deelzaaktypen) on concept zaaktypen.
            // If already published, unpublish first by forcing concept state via the force-publish workaround
            // is not available — instead we rely on Open Zaak allowing PATCH when concept=true.
            // If the zaaktype is published we attempt the PATCH anyway; some Open Zaak versions allow it.
            if (! $isConcept) {
                $this->line('    Zaaktype is gepubliceerd. Terugzetten naar concept...');

                $conceptResponse = Http::withHeaders($headers)->patch(
                    $baseUrl."zaaktypen/{$uuid}",
                    ['concept' => true]
                );

                if (! $conceptResponse->successful()) {
                    $urlList = collect($expectedUrls)->map(fn ($url) => "      - {$url}")->implode("\n");
                    $this->warn(
                        "    Terugzetten naar concept mislukt (HTTP {$conceptResponse->status()}).\n".
                        "    Stel deelzaaktypen handmatig in via Open Zaak beheer — verwacht ({$count}):\n".
                        $urlList
                    );
                    $failed++;

                    continue;
                }
            }

            // PATCH deelzaaktypen
            $patchResponse = Http::withHeaders($headers)->patch(
                $baseUrl."zaaktypen/{$uuid}",
                ['deelzaaktypen' => $expectedUrls]
            );

            if (! $patchResponse->successful()) {
                // Restore published state if we unpublished
                if (! $isConcept) {
                    Http::withHeaders($headers)->post($baseUrl."zaaktypen/{$uuid}/publish");
                }

                $urlList = collect($expectedUrls)->map(fn ($url) => "      - {$url}")->implode("\n");
                $this->warn(
                    "    PATCH mislukt (HTTP {$patchResponse->status()}).\n".
                    "    Stel deelzaaktypen handmatig in via Open Zaak beheer — verwacht ({$count}):\n".
                    $urlList
                );
                $failed++;

                continue;
            }

            // Re-publish if it was published before
            if (! $isConcept) {
                $publishResponse = Http::withHeaders($headers)->post($baseUrl."zaaktypen/{$uuid}/publish");

                if (! $publishResponse->successful()) {
                    $this->warn("    Deelzaaktypen bijgewerkt maar herpubliceren mislukt (HTTP {$publishResponse->status()}). Publiceer handmatig.");
                } else {
                    $this->line('    Hergepubliceerd.');
                }
            }

            $this->line("  <info>Deelzaaktypen bijgewerkt</info>: {$evenementenZaaktype->name}");
            $updated++;
        }

        $this->newLine();
        $this->info("Klaar. Bijgewerkt: {$updated}, al correct: {$alreadyCorrect}, mislukt: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
