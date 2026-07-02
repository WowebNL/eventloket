<?php

declare(strict_types=1);

namespace App\Console\Commands\Zaaktypen;

use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use Illuminate\Console\Command;

/**
 * One-off, idempotent backfill for the per-municipality zaaktype blueprint
 * (Onderdeel 5). Seeds one MunicipalityZaaktypeMapping per (municipality x role)
 * from the current name conventions, so the explicit mapping matches what the
 * heuristics already resolve for our own OpenZaak.
 *
 * Seeded per row:
 *  - zaaktype_identificatie: the logical Zaaktype matching the role's name
 *    prefix (or the municipality's doorkomst_zaaktype for the Doorkomst role).
 *  - eigenschap_map: the identity map (our catalogus names eigenschappen
 *    exactly like their logical key).
 *  - flow-blockers (statustype/roltype/resultaattype/informatieobjecttype):
 *    left null, so the original heuristic keeps applying. For our own OpenZaak
 *    that heuristic is exactly correct; a koppeling beheerder fills these in
 *    only when wiring an external catalogus whose conventions differ.
 *
 * Run this AFTER app:backfill-zaaktype-identificatie (it needs the logical
 * Zaaktype.identificatie to be populated). Re-running is safe: existing rows
 * are skipped, never overwritten.
 */
class BackfillZaaktypeMappings extends Command
{
    protected $signature = 'app:backfill-zaaktype-mappings {--dry-run : Toon wat er zou gebeuren zonder te schrijven}';

    protected $description = 'Seed de per-gemeente zaaktype-blueprint vanuit de huidige naamconventies (idempotent)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: er worden geen wijzigingen doorgevoerd.');
        }

        $created = 0;
        $skipped = 0;

        foreach (Municipality::with('doorkomstZaaktype')->get() as $municipality) {
            foreach (ZaaktypeRole::cases() as $role) {
                $zaaktype = $this->resolveZaaktype($municipality, $role);
                if (! $zaaktype || ! $zaaktype->identificatie) {
                    continue;
                }

                $exists = MunicipalityZaaktypeMapping::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('role', $role->value)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $this->line(sprintf('  %s / %s → %s', $municipality->name, $role->value, $zaaktype->identificatie));

                if (! $dryRun) {
                    MunicipalityZaaktypeMapping::create([
                        'municipality_id' => $municipality->id,
                        'role' => $role->value,
                        'zaaktype_identificatie' => $zaaktype->identificatie,
                        'eigenschap_map' => ZaakeigenschappenMap::defaultEigenschapMap(),
                    ]);
                }

                $created++;
            }
        }

        $this->info("Klaar. Aangemaakt: {$created}, overgeslagen (bestond al): {$skipped}.");

        return Command::SUCCESS;
    }

    private function resolveZaaktype(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        if ($role === ZaaktypeRole::Doorkomst) {
            /** @var Zaaktype|null $doorkomst */
            $doorkomst = $municipality->doorkomstZaaktype;

            return $doorkomst;
        }

        return Zaaktype::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->where('name', 'like', $role->namePrefix().'%')
            ->first();
    }
}
