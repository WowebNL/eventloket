<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Console\Command;

class LinkZaaktypenToMunicipalities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:link-zaaktypen-municipalities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Links zaaktypen to municipalities by extracting the municipality name from the zaaktype name (e.g. "Melding evenement gemeente Heerlen")';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Linking zaaktypen to municipalities...');

        $zaaktypen = Zaaktype::all();

        if ($zaaktypen->isEmpty()) {
            $this->warn('No zaaktypen found. Run app:sync-zaaktypen first.');

            return Command::FAILURE;
        }

        // Pre-load all municipalities, keyed by lowercase name for fast lookup
        $municipalities = Municipality::all()->keyBy(fn ($m) => strtolower($m->name));

        $linked = 0;
        $skipped = 0;
        $alreadyCorrect = 0;

        foreach ($zaaktypen as $zaaktype) {
            // Only process zaaktypen that are properly synced from Open Zaak (have a URL)
            if (empty($zaaktype->zgw_zaaktype_url)) {
                $this->line("  <comment>Skipped</comment> (no zgw_zaaktype_url set): {$zaaktype->name}");
                $skipped++;

                continue;
            }

            // Extract municipality name from patterns like:
            //   "Melding evenement gemeente Heerlen"
            //   "Vooraankondiging gemeente Gulpen-Wittem"
            if (! preg_match('/\bgemeente\s+(.+)$/iu', $zaaktype->name, $matches)) {
                $this->line("  <comment>Skipped</comment> (no municipality in name): {$zaaktype->name}");
                $skipped++;

                continue;
            }

            $municipalityName = trim($matches[1]);
            $municipality = $municipalities->get(strtolower($municipalityName));

            if ($municipality === null) {
                $this->line("  <comment>Skipped</comment> (municipality not found): \"{$municipalityName}\" — from zaaktype: {$zaaktype->name}");
                $skipped++;

                continue;
            }

            if ($zaaktype->municipality_id === $municipality->id) {
                $alreadyCorrect++;

                continue;
            }

            $zaaktype->municipality_id = $municipality->id;
            $zaaktype->save();

            $this->line("  <info>Linked</info>: {$zaaktype->name} → {$municipality->name}");
            $linked++;
        }

        $this->info("Done. Linked: {$linked}, already correct: {$alreadyCorrect}, skipped: {$skipped}.");

        return Command::SUCCESS;
    }
}
