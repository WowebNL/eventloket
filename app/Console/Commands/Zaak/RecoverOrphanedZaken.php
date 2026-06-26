<?php

declare(strict_types=1);

namespace App\Console\Commands\Zaak;

use App\Jobs\Zaak\AddGeometryZGW;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\Services\Zgw\ZaakReadModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

/**
 * Eenmalig herstel van v0.x-zaken die nooit lokaal zijn aangemaakt.
 *
 * In v0.x liep de ingest als een `Bus::chain`:
 *   AddZaakeigenschappenZGW -> AddEinddatumZGW -> UpdateInitiatorZGW (3)
 *   -> AddGeometryZGW (4) -> CreateZaak (5) -> CreateDoorkomstZaken (6)
 * `UpdateInitiatorZGW` faalde op de adresvalidatie en stopte de chain, dus
 * `CreateZaak` (positie 5) heeft de lokale `zaken`-row nooit geschreven. De
 * zaak, documenten en de OF-initiator-rol bestaan wel in OpenZaak.
 *
 * Dit command herbouwt de lokale row (port van de verwijderde `CreateZaak`-job)
 * zodat de zaak met bestanden weer zichtbaar wordt, en draait optioneel de
 * overgeslagen geometrie-stap opnieuw. De initiator-rol blijft ongemoeid (OF's
 * rol bestaat al; opnieuw POSTen zou dupliceren).
 */
class RecoverOrphanedZaken extends Command
{
    protected $signature = 'zaak:recover-orphaned
        {url?* : One or more zgw_zaak_url values to recover}
        {--from-failed-jobs : Discover urls by scanning failed_jobs for UpdateInitiatorZGW}
        {--no-geometry : Skip re-running AddGeometryZGW (visibility-only recovery)}
        {--dry-run : Report what would be created without writing}';

    protected $description = 'Recreate local zaken rows for v0.x cases orphaned by a failed UpdateInitiatorZGW chain.';

    public function handle(Openzaak $openzaak, ObjectsApi $objectsapi): int
    {
        $urls = $this->resolveUrls();

        if ($urls === []) {
            $this->error('No zgw_zaak_url values provided. Pass urls as arguments or use --from-failed-jobs.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $withGeometry = ! $this->option('no-geometry');

        $this->info(sprintf('%d case(s) to process%s.', count($urls), $dryRun ? ' (dry-run)' : ''));

        $recovered = 0;
        foreach ($urls as $url) {
            if ($this->recoverOne($url, $openzaak, $objectsapi, $dryRun, $withGeometry)) {
                $recovered++;
            }
        }

        $this->newLine();
        $this->info(sprintf('Done. %d/%d case(s) %s.', $recovered, count($urls), $dryRun ? 'would be recovered' : 'recovered'));
        $this->printFailedJobHint();

        return self::SUCCESS;
    }

    private function recoverOne(
        string $url,
        Openzaak $openzaak,
        ObjectsApi $objectsapi,
        bool $dryRun,
        bool $withGeometry,
    ): bool {
        if (Zaak::where('zgw_zaak_url', $url)->exists()) {
            $this->line("- {$url}: already present locally, skipping.");

            return false;
        }

        try {
            $ozZaak = ZaakReadModel::fromArray($openzaak->get($url.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen')->toArray());
        } catch (Throwable $e) {
            $this->error("- {$url}: failed to fetch ZGW case ({$e->getMessage()}).");

            return false;
        }

        $zaaktype = $this->resolveZaaktype($ozZaak->zaaktype, $openzaak);
        if (! $zaaktype) {
            $this->warn("- {$url}: zaaktype not found or inactive ({$ozZaak->zaaktype}), skipping.");

            return false;
        }

        [$organisation, $user] = $this->resolveOrganisationAndUser($ozZaak, $objectsapi);
        $organisator = $this->buildOrganisatorLabel($ozZaak);

        $this->line(sprintf(
            '- %s: %s | zaaktype=%s | organisation=%s | user=%s | organisator=%s',
            $url,
            $ozZaak->identificatie,
            $zaaktype->id,
            $organisation ? $organisation->id : 'null',
            $user ? $user->id : 'null',
            $organisator !== '' ? $organisator : 'null',
        ));

        if ($dryRun) {
            return true;
        }

        $zaak = Zaak::updateOrCreate(
            ['zgw_zaak_url' => $ozZaak->url],
            [
                'public_id' => $ozZaak->identificatie,
                'zaaktype_id' => $zaaktype->id,
                'zgw_zaaktype_url' => $ozZaak->zaaktype, // snapshot of the version used
                'data_object_url' => $ozZaak->data_object_url,
                'organisation_id' => $organisation?->id,
                'organiser_user_id' => $user?->id,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        $ozZaak->eigenschappen_key_value,
                        [
                            'registratiedatum' => $ozZaak->registratiedatum,
                            'status_name' => $ozZaak->status_name ?? '',
                            'statustype_url' => $ozZaak->statustype_url ?? '',
                            'organisator' => $organisator,
                        ]
                    )
                ),
            ]
        );

        if ($withGeometry) {
            $this->reAddGeometry($zaak);
        }

        return true;
    }

    /**
     * Resolve the local logical zaaktype for a ZGW zaaktype version url.
     *
     * Tries an exact url match first; after version-collapsing the local row holds
     * the latest version url, so an older version on an orphaned zaak falls back to
     * a lookup by that version's logical identificatie.
     */
    private function resolveZaaktype(string $versionUrl, Openzaak $openzaak): ?Zaaktype
    {
        $zaaktype = Zaaktype::where(['zgw_zaaktype_url' => $versionUrl, 'is_active' => true])->first();
        if ($zaaktype) {
            return $zaaktype;
        }

        try {
            $identificatie = $openzaak->get($versionUrl)->toArray()['identificatie'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return $identificatie
            ? Zaaktype::where(['identificatie' => $identificatie, 'is_active' => true])->first()
            : null;
    }

    /**
     * Re-run the geometry step the halted chain skipped. AddGeometryZGW reads the
     * event_location from form_state_snapshot, which old cases lack, so first
     * populate it from the Objects record, then dispatch the (idempotent) job.
     */
    private function reAddGeometry(Zaak $zaak): void
    {
        if (! $zaak->data_object_url) {
            $this->warn("  {$zaak->public_id}: no data_object_url, cannot backfill snapshot for geometry.");

            return;
        }

        Artisan::call('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id]);
        AddGeometryZGW::dispatch($zaak->refresh());
        $this->line("  {$zaak->public_id}: snapshot backfilled and AddGeometryZGW dispatched.");
    }

    /**
     * Resolve the local organisation and organiser user from the Objects record.
     * Replaces the deleted FormSubmissionObject value object. A failing Objects
     * API lookup must not block row creation (org/user simply stay null).
     *
     * @return array{0: ?Organisation, 1: ?OrganiserUser}
     */
    private function resolveOrganisationAndUser(ZaakReadModel $ozZaak, ObjectsApi $objectsapi): array
    {
        if (! $ozZaak->data_object_url) {
            return [null, null];
        }

        try {
            $object = $objectsapi->get(basename($ozZaak->data_object_url))->toArray();
        } catch (Throwable $e) {
            Log::warning('RecoverOrphanedZaken: Objects API lookup failed', ['zaak' => $ozZaak->url, 'error' => $e->getMessage()]);

            return [null, null];
        }

        $prefix = strtolower((string) config('app.name'));
        $organisationUuid = data_get($object, "record.data.{$prefix}_organisation_uuid");
        $userUuid = data_get($object, "record.data.{$prefix}_user_uuid");

        return [
            $organisationUuid ? Organisation::where('uuid', $organisationUuid)->first() : null,
            $userUuid ? OrganiserUser::where('uuid', $userUuid)->first() : null,
        ];
    }

    private function buildOrganisatorLabel(ZaakReadModel $ozZaak): string
    {
        $initiator = $ozZaak->initiator;
        if (! $initiator) {
            return '';
        }

        $type = $initiator['betrokkeneType'] ?? null;
        $id = $initiator['betrokkeneIdentificatie'] ?? [];

        if ($type === 'natuurlijk_persoon') {
            return trim(($id['voornamen'] ?? '').' '.($id['geslachtsnaam'] ?? ''));
        }

        if ($type === 'niet_natuurlijk_persoon') {
            return ($id['statutaireNaam'] ?? '').' - '.($initiator['contactpersoonRol']['naam'] ?? '');
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function resolveUrls(): array
    {
        /** @var list<string> $urls */
        $urls = $this->argument('url') ?? [];

        if ($this->option('from-failed-jobs')) {
            $urls = array_merge($urls, $this->discoverUrlsFromFailedJobs());
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Pull zgw_zaak_url values out of failed UpdateInitiatorZGW payloads. The old
     * job serialized the url under a `zaakUrlZGW` property; rather than unserialize
     * a deleted class, match the url with a regex over the command string.
     *
     * @return list<string>
     */
    private function discoverUrlsFromFailedJobs(): array
    {
        $rows = DB::table('failed_jobs')
            ->where('payload', 'like', '%UpdateInitiatorZGW%')
            ->pluck('payload');

        $urls = [];
        foreach ($rows as $payload) {
            $command = (string) data_get(json_decode((string) $payload, true), 'data.command');
            if (preg_match('#https?://[^"\\\\\s]+/zaken/[^"\\\\\s]+#', $command, $m)) {
                $urls[] = rtrim($m[0], '";');
            }
        }

        $found = array_values(array_unique($urls));
        $this->info(sprintf('Discovered %d url(s) from failed_jobs.', count($found)));
        foreach ($found as $u) {
            $this->line("  {$u}");
        }

        return $found;
    }

    private function printFailedJobHint(): void
    {
        $uuids = DB::table('failed_jobs')
            ->where('payload', 'like', '%UpdateInitiatorZGW%')
            ->pluck('uuid');

        if ($uuids->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->comment('Stale UpdateInitiatorZGW failed_jobs can be removed once recovery is confirmed:');
        foreach ($uuids as $uuid) {
            $this->line("  php artisan queue:forget {$uuid}");
        }
    }
}
