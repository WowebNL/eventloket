<?php

namespace App\Console\Commands\Doorkomst;

use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

class SyncDeelzaaktypen extends Command
{
    protected $signature = 'app:sync-deelzaaktypen';

    protected $description = 'Koppelt doorkomst zaaktypen als deelzaaktypen aan evenementenvergunning zaaktypen in onze eigen Open Zaak';

    public function handle(ZgwConnectionResolver $resolver): int
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

        $updated = 0;
        $alreadyCorrect = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($evenementenZaaktypen as $evenementenZaaktype) {
            $connectionName = $evenementenZaaktype->zgwConnectionName();
            $connection = Zgw::connection($connectionName);

            // All doorkomst zaaktypen except the one belonging to the same municipality
            $expectedUrls = $doorkomstZaaktypen
                ->where('municipality_id', '!=', $evenementenZaaktype->municipality_id)
                ->pluck('zgw_zaaktype_url')
                ->values()
                ->toArray();

            sort($expectedUrls);

            $uuid = basename((string) $evenementenZaaktype->zgw_zaaktype_url);

            try {
                $currentData = $connection->catalogi()->zaaktypen()->show($uuid);
            } catch (Throwable $exception) {
                $this->warn("  Ophalen mislukt voor {$evenementenZaaktype->name}: {$exception->getMessage()}");
                $failed++;

                continue;
            }

            $currentUrls = $currentData['deelzaaktypen'] ?? [];
            sort($currentUrls);

            if ($currentUrls === $expectedUrls) {
                $this->line("  <comment>Al correct</comment>: {$evenementenZaaktype->name}");
                $alreadyCorrect++;

                continue;
            }

            $count = count($expectedUrls);

            // Deelzaaktypen linking is a structural write. Externally managed instances
            // are left untouched: the koppeling beheerder sets these up in their own catalogus.
            if (! $resolver->isManaged($connectionName)) {
                $this->warn(
                    "  Overgeslagen (externe connectie '{$connectionName}'): {$evenementenZaaktype->name}. ".
                    "Stel de {$count} deelzaaktypen handmatig in via de externe catalogus (read-only)."
                );
                $skipped++;

                continue;
            }

            $this->line("  Bijwerken: {$evenementenZaaktype->name} ({$count} deelzaaktypen verwacht)");

            try {
                $connection->catalogi()->zaaktypen()->patch($uuid, ['deelzaaktypen' => $expectedUrls]);
            } catch (Throwable $exception) {
                $urlList = collect($expectedUrls)->map(fn ($url) => "      - {$url}")->implode("\n");
                $this->warn(
                    "    Bijwerken mislukt: {$exception->getMessage()}\n".
                    "    Een gepubliceerd zaaktype is immutable; pas de deelzaaktypen aan op een concept-versie of stel ze handmatig in ({$count}):\n".
                    $urlList
                );
                $failed++;

                continue;
            }

            $this->line("  <info>Deelzaaktypen bijgewerkt</info>: {$evenementenZaaktype->name}");
            $updated++;
        }

        $this->newLine();
        $this->info("Klaar. Bijgewerkt: {$updated}, al correct: {$alreadyCorrect}, mislukt: {$failed}, overgeslagen (extern): {$skipped}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
