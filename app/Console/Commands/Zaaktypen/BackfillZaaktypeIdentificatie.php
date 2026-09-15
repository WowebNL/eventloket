<?php

namespace App\Console\Commands\Zaaktypen;

use App\Models\Zaaktype;
use App\Services\Zgw\ZgwResource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off, idempotent backfill for the zaaktype versioning change (Onderdeel 4).
 *
 * Pre-existing rows were one-per-version with no identificatie. This command:
 *  1. fetches the identificatie for every zaaktype row from the ZGW catalogus,
 *  2. collapses rows that share an identificatie (per municipality) into one
 *     logical row, repointing their zaken to the survivor and snapshotting the
 *     exact version url each zaak used on the zaken row,
 *  3. leaves the survivor's zgw_zaaktype_url as-is (the next app:sync-zaaktypen
 *     run advances it to the latest version).
 *
 * Run this once after deploying the migration, before the next app:sync-zaaktypen.
 * Re-running is safe: rows that already have an identificatie are skipped.
 */
class BackfillZaaktypeIdentificatie extends Command
{
    protected $signature = 'app:backfill-zaaktype-identificatie {--dry-run : Toon wat er zou gebeuren zonder te schrijven}';

    protected $description = 'Backfill identificatie op zaaktypen, voeg dubbele versie-rijen samen en snapshot de versie op bestaande zaken';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: er worden geen wijzigingen doorgevoerd.');
        }

        $this->backfillIdentificatie($dryRun);
        $this->collapseDuplicates($dryRun);

        $this->info('Klaar.');

        return Command::SUCCESS;
    }

    private function backfillIdentificatie(bool $dryRun): void
    {
        $rows = Zaaktype::whereNull('identificatie')->whereNotNull('zgw_zaaktype_url')->get();

        $this->info("Identificatie backfillen voor {$rows->count()} zaaktypen zonder identificatie...");

        foreach ($rows as $zaaktype) {
            try {
                $identificatie = ZgwResource::byUrl($zaaktype->zgwConnectionName(), (string) $zaaktype->zgw_zaaktype_url)['identificatie'] ?? null;
            } catch (Throwable $e) {
                $this->warn("  Ophalen mislukt voor {$zaaktype->name}: {$e->getMessage()}");

                continue;
            }

            if ($identificatie === null || $identificatie === '') {
                $this->warn("  Geen identificatie gevonden voor {$zaaktype->name} ({$zaaktype->zgw_zaaktype_url}).");

                continue;
            }

            $this->line("  {$zaaktype->name} → {$identificatie}");

            if (! $dryRun) {
                $zaaktype->update(['identificatie' => $identificatie]);
            }
        }
    }

    private function collapseDuplicates(bool $dryRun): void
    {
        $this->info('Dubbele versie-rijen samenvoegen...');

        $groups = Zaaktype::whereNotNull('identificatie')
            ->get()
            ->groupBy(fn (Zaaktype $z) => $z->identificatie.'|'.($z->municipality_id ?? 'null'))
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->line('  Geen duplicaten gevonden.');

            return;
        }

        foreach ($groups as $key => $group) {
            // Deterministic survivor selection (by version url); the next
            // app:sync-zaaktypen run advances the survivor to the latest version.
            $ordered = $group->sortBy('zgw_zaaktype_url')->values();
            $survivor = $ordered->first();
            $donors = $ordered->slice(1);

            $this->line("  {$key}: behoud 1, voeg {$donors->count()} samen.");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($survivor, $donors) {
                foreach ($donors as $donor) {
                    // Snapshot the version each zaak used, then repoint to the survivor.
                    // Order matters: repoint before deleting (zaken cascade on delete).
                    $donor->zaken()->whereNull('zgw_zaaktype_url')->update(['zgw_zaaktype_url' => $donor->zgw_zaaktype_url]);
                    $donor->zaken()->update(['zaaktype_id' => $survivor->id]);
                    $donor->delete();
                }
            });
        }
    }
}
