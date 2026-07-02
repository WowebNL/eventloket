<?php

namespace App\Console\Commands\Zaaktypen;

use App\Enums\ZaaktypeRefreshStatus;
use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaaktypeRefresher;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;
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
    protected $description = 'Syncs zaaktypen from the connected Open Zaak instance(s) and links them to municipalities';

    /**
     * Execute the console command.
     */
    public function handle(ZaaktypeRefresher $refresher)
    {
        $this->info('Syncing zaaktypen...');

        // The shared "main" catalogus. Its rows are linked to municipalities by
        // name afterwards (see syncMunicipalityLinks).
        $this->syncConnection(ZgwConnectionResolver::DEFAULT_CONNECTION);

        $this->info('Zaaktypen synced successfully.');

        $this->syncMunicipalityLinks();

        // Municipalities with their own ZGW instance only get local rows for the
        // zaaktypen they actually use (their mappings), read one by one from their
        // own instance rather than importing the whole external catalogus.
        $this->refreshOwnInstanceZaaktypen($refresher);

        return Command::SUCCESS;
    }

    /**
     * Sync the main catalogus into local zaaktype rows.
     *
     * Rows are keyed by (identificatie, connection) so the same identificatie can
     * exist in both the main catalogus and a municipality's own instance.
     * Deactivation is scoped to this connection, so it never touches own-instance rows.
     */
    private function syncConnection(string $connectionName): void
    {
        try {
            $zaaktypen = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index();
        } catch (Throwable $e) {
            $this->warn("  Kon zaaktypen niet ophalen voor connectie {$connectionName}: {$e->getMessage()}");

            return;
        }

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
                ['identificatie' => $identificatie, 'connection' => $connectionName],
                [
                    'zgw_zaaktype_url' => $data['url'],
                    'name' => $data['omschrijving'],
                    'is_active' => true,
                ],
            );

            // Default the role from the name prefix when no role is set yet, so
            // role-based filters work out of the box. An admin choice is preserved
            // across syncs because we never overwrite an existing role.
            if ($zaaktype->role === null) {
                $role = ZaaktypeRole::fromName($zaaktype->name);
                if ($role !== null) {
                    $zaaktype->role = $role;
                    $zaaktype->save();
                }
            }

            $updatedIds[] = $zaaktype->id;
        }

        // Deactivate this connection's zaaktypen that were not in the response.
        Zaaktype::where('connection', $connectionName)
            ->whereNotIn('id', $updatedIds)
            ->update(['is_active' => false]);

        $this->line("  Connectie {$connectionName}: ".count($updatedIds).' zaaktypen.');
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

    /**
     * Link the main catalogus' zaaktypen to municipalities by parsing the
     * municipality name from the zaaktype name, and set the doorkomst zaaktype.
     *
     * Municipalities with their own ZGW instance are skipped: their zaaktypen come
     * from that instance (synced above with municipality_id already set), so they
     * must not also be linked to a main zaaktype with the same name.
     */
    private function syncMunicipalityLinks(): void
    {
        $this->info('Linking zaaktypen to municipalities...');

        /** @var Collection<string, Municipality> $municipalities */
        $municipalities = Municipality::all()->keyBy(fn (Municipality $m) => strtolower($m->name));

        $ownInstanceMunicipalityIds = MunicipalityZgwConnection::query()->pluck('municipality_id')->all();

        // Unlink inactive main zaaktypen from their municipality.
        Zaaktype::where('connection', ZgwConnectionResolver::DEFAULT_CONNECTION)
            ->where('is_active', false)
            ->whereNotNull('municipality_id')
            ->update(['municipality_id' => null]);

        /** @var Zaaktype $zaaktype */
        foreach (Zaaktype::where('connection', ZgwConnectionResolver::DEFAULT_CONNECTION)->where('is_active', true)->get() as $zaaktype) {
            if (! preg_match('/\bgemeente\s+(.+)$/iu', $zaaktype->name, $matches)) {
                continue;
            }

            $municipalityName = trim($matches[1]);
            $municipality = $municipalities->get(strtolower($municipalityName));

            if ($municipality === null) {
                $this->line("  <comment>Gemeente niet gevonden</comment>: \"$municipalityName\" (zaaktype: {$zaaktype->name})");

                continue;
            }

            // The municipality uses its own ZGW instance; main zaaktypen are not
            // linked to it.
            if (in_array($municipality->id, $ownInstanceMunicipalityIds, true)) {
                continue;
            }

            if ($zaaktype->municipality_id !== $municipality->id) {
                $zaaktype->municipality_id = $municipality->id;
                $zaaktype->save();
            }

            // For doorkomst zaaktypen, also set doorkomst_zaaktype_id on the municipality.
            if (str_contains($zaaktype->name, 'Doorkomst') && $municipality->doorkomst_zaaktype_id !== $zaaktype->id) {
                $municipality->doorkomst_zaaktype_id = $zaaktype->id;
                $municipality->save();
                $this->line("  <info>Doorkomst zaaktype gekoppeld</info>: {$municipality->name} → {$zaaktype->name}");
            }
        }

        $this->info('Municipalities gesynchroniseerd.');
    }

    /**
     * Targeted refresh of own-instance zaaktypen: for each municipality that runs
     * its own ZGW instance, refresh only the distinct identificaties it has mapped,
     * reading each one from its own instance via {@see ZaaktypeRefresher}, so the
     * sync engages the same fallback/restore transitions and warnings as the
     * zaaktypen-kanaal webhook.
     */
    private function refreshOwnInstanceZaaktypen(ZaaktypeRefresher $refresher): void
    {
        $this->info('Refreshing own-instance zaaktypen...');

        foreach (MunicipalityZgwConnection::query()->with('municipality')->get() as $connection) {
            $municipality = $connection->municipality;

            if ($municipality === null) {
                continue;
            }

            $identificaties = MunicipalityZaaktypeMapping::query()
                ->where('municipality_id', $municipality->id)
                ->whereNotNull('zaaktype_identificatie')
                ->distinct()
                ->pluck('zaaktype_identificatie');

            foreach ($identificaties as $identificatie) {
                $result = $refresher->refreshOwnInstance($municipality, $identificatie);

                if ($result->status === ZaaktypeRefreshStatus::Refreshed) {
                    $this->line("  Gemeente {$municipality->id}: zaaktype {$identificatie} ververst.");
                }
            }
        }
    }
}
