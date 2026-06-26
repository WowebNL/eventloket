<?php

namespace App\Console\Commands\Zaaktypen;

use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Woweb\Zgw\Facades\Zgw;

class SyncZaaktypen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-zaaktypen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs zaaktypen from the connected Open Zaak instance and links them to municipalities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing zaaktypen...');

        // Fetch zaaktypen from the central ZGW (OpenZaak) catalogus.
        $zaaktypen = Zgw::connection()->catalogi()->zaaktypen()->index();

        // A single identificatie spans every version of a zaaktype. We keep one
        // logical row per identificatie holding the latest version url; the version
        // valid on a zaak's creation date is resolved at zaak-creation time.
        $latestByIdentificatie = [];
        foreach ($zaaktypen as $data) {
            $identificatie = $data['identificatie'] ?? null;
            if ($identificatie === null || $identificatie === '') {
                $this->line("  <comment>Overgeslagen</comment> (geen identificatie): {$data['url']}");

                continue;
            }

            $current = $latestByIdentificatie[$identificatie] ?? null;
            if ($current === null || $this->isNewerVersion($data, $current)) {
                $latestByIdentificatie[$identificatie] = $data;
            }
        }

        $updatedIds = [];
        foreach ($latestByIdentificatie as $identificatie => $data) {
            $zaaktype = Zaaktype::updateOrCreate(
                ['identificatie' => $identificatie],
                [
                    'zgw_zaaktype_url' => $data['url'],
                    'name' => $data['omschrijving'],
                    'is_active' => true,
                ]
            );
            $updatedIds[] = $zaaktype->id;
        }

        // Deactivate all zaaktypen that were not in the Open Zaak response
        Zaaktype::whereNotIn('id', $updatedIds)->update(['is_active' => false]);

        $this->info('Zaaktypen synced successfully.');

        // Link active zaaktypen to municipalities, set doorkomst zaaktype, and unlink inactive ones
        $this->syncMunicipalityLinks();

        return Command::SUCCESS;
    }

    /**
     * Whether $candidate is a newer version than $current, comparing the ZTC
     * validity dates (beginGeldigheid, then versiedatum as a tiebreaker). Both are
     * ISO Y-m-d strings, so a plain string comparison preserves chronological order.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $current
     */
    private function isNewerVersion(array $candidate, array $current): bool
    {
        $candidateKey = ((string) ($candidate['beginGeldigheid'] ?? '')).'|'.((string) ($candidate['versiedatum'] ?? ''));
        $currentKey = ((string) ($current['beginGeldigheid'] ?? '')).'|'.((string) ($current['versiedatum'] ?? ''));

        return $candidateKey > $currentKey;
    }

    private function syncMunicipalityLinks(): void
    {
        $this->info('Linking zaaktypen to municipalities...');

        /** @var Collection<int, Municipality> $municipalities */
        $municipalities = Municipality::all()->keyBy(fn (Municipality $m) => strtolower($m->name));

        $linked = 0;
        $unlinked = 0;
        $skipped = 0;

        // Unlink all inactive zaaktypen from their municipality
        $inactiveUnlinked = Zaaktype::where('is_active', false)
            ->whereNotNull('municipality_id')
            ->count();

        Zaaktype::where('is_active', false)->update(['municipality_id' => null]);

        if ($inactiveUnlinked > 0) {
            $this->line("  Ontkoppeld (inactief): $inactiveUnlinked zaaktypen.");
        }

        // Link active zaaktypen to municipalities by extracting the municipality name
        /** @var Zaaktype $zaaktype */
        foreach (Zaaktype::where('is_active', true)->get() as $zaaktype) {
            if (! preg_match('/\bgemeente\s+(.+)$/iu', $zaaktype->name, $matches)) {
                $skipped++;

                continue;
            }

            $municipalityName = trim($matches[1]);
            $municipality = $municipalities->get(strtolower($municipalityName));

            if ($municipality === null) {
                $this->line("  <comment>Gemeente niet gevonden</comment>: \"$municipalityName\" (zaaktype: {$zaaktype->name})");
                $skipped++;

                continue;
            }

            $changed = false;

            if ($zaaktype->municipality_id !== $municipality->id) {
                $zaaktype->municipality_id = $municipality->id;
                $changed = true;
            }

            if ($changed) {
                $zaaktype->save();
                $linked++;
            }

            // For doorkomst zaaktypen, also set doorkomst_zaaktype_id on the municipality
            if (str_contains($zaaktype->name, 'Doorkomst') && $municipality->doorkomst_zaaktype_id !== $zaaktype->id) {
                $municipality->doorkomst_zaaktype_id = $zaaktype->id;
                $municipality->save();
                $this->line("  <info>Doorkomst zaaktype gekoppeld</info>: {$municipality->name} → {$zaaktype->name}");
            }
        }

        $this->info("Municipalities gesynchroniseerd. Gekoppeld: $linked, overgeslagen: $skipped, ontkoppeld (inactief): $inactiveUnlinked.");
    }
}
