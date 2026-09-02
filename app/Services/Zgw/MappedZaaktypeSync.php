<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\ZaaktypeRefreshStatus;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\ZaaktypeRefreshResult;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates or refreshes the single local {@see Zaaktype} row a municipality needs
 * for a zaaktype it actually uses, reading only that one zaaktype from its own
 * ZGW instance instead of importing the whole external catalogus.
 *
 * Every zaak requires a local Zaaktype row (zaken.zaaktype_id is a NOT NULL FK
 * and ResolveZaaktype looks up by municipality_id + identificatie), so own-instance
 * municipalities still get rows, but limited to the identificaties they have
 * mapped in a {@see MunicipalityZaaktypeMapping}.
 */
final class MappedZaaktypeSync
{
    public function __construct(private readonly ZgwConnectionResolver $resolver) {}

    /**
     * Ensure a local row exists for (municipality, identificatie), read from the
     * municipality's own ZGW instance. Returns the row, or null when the
     * municipality has no own connection or the catalogus read fails/yields nothing.
     */
    public function ensure(Municipality $municipality, string $identificatie): ?Zaaktype
    {
        $result = $this->refresh($municipality, $identificatie);

        return $result->status === ZaaktypeRefreshStatus::Refreshed ? $result->zaaktype : null;
    }

    /**
     * Refresh the local row for (municipality, identificatie) and report what
     * happened, distinguishing the outcomes {@see ensure()} conflates:
     *
     * - Refreshed: a definitief version exists; the row was created or updated.
     * - Unavailable: the catalogus answered but no definitief version exists
     *   anymore; an existing row is deactivated so it stops resolving at submit.
     * - Failed: the catalogus could not be read, or this sync does not apply
     *   (no own connection). Nothing changes: a transient outage must not flip
     *   routing or trigger a fallback.
     */
    public function refresh(Municipality $municipality, string $identificatie): ZaaktypeRefreshResult
    {
        if ($identificatie === '') {
            return new ZaaktypeRefreshResult(ZaaktypeRefreshStatus::Failed);
        }

        // Resolving registers the per-municipality connection config. This is a
        // management sync, so it uses the connection regardless of activation (a
        // koppeling is configured before going live). An invalid or absent config
        // falls back to "main", which this targeted sync skips: main rows are
        // linked by name in SyncZaaktypen, not created here.
        $connectionName = $this->resolver->forManagement($municipality);

        if ($connectionName === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return new ZaaktypeRefreshResult(ZaaktypeRefreshStatus::Failed);
        }

        try {
            $version = ZaaktypeVersion::currentDefinitief($connectionName, $identificatie);
        } catch (Throwable $e) {
            Log::warning('MappedZaaktypeSync: kon zaaktype niet ophalen', [
                'municipality_id' => $municipality->id,
                'identificatie' => $identificatie,
                'connection' => $connectionName,
                'exception' => $e->getMessage(),
            ]);

            return new ZaaktypeRefreshResult(ZaaktypeRefreshStatus::Failed);
        }

        if ($version === null) {
            return $this->deactivate($connectionName, $identificatie);
        }

        return $this->upsert($municipality, $connectionName, $identificatie, $version);
    }

    /**
     * No definitief version exists anymore: deactivate the local row so
     * ResolveZaaktype stops matching it. The row itself is kept (existing zaken
     * reference it and it revives on the next successful refresh).
     */
    private function deactivate(string $connectionName, string $identificatie): ZaaktypeRefreshResult
    {
        $zaaktype = Zaaktype::query()
            ->where('identificatie', $identificatie)
            ->where('connection', $connectionName)
            ->first();

        if ($zaaktype === null) {
            return new ZaaktypeRefreshResult(ZaaktypeRefreshStatus::Unavailable);
        }

        $becameInactive = $zaaktype->is_active;

        if ($becameInactive) {
            $zaaktype->is_active = false;
            $zaaktype->save();
        }

        return new ZaaktypeRefreshResult(
            ZaaktypeRefreshStatus::Unavailable,
            $zaaktype,
            becameInactive: $becameInactive,
        );
    }

    /**
     * @param  array<string, mixed>  $version
     */
    private function upsert(Municipality $municipality, string $connectionName, string $identificatie, array $version): ZaaktypeRefreshResult
    {
        $role = MunicipalityZaaktypeMapping::query()
            ->where('municipality_id', $municipality->id)
            ->where('zaaktype_identificatie', $identificatie)
            ->value('role');

        $zaaktype = Zaaktype::updateOrCreate(
            ['identificatie' => $identificatie, 'connection' => $connectionName],
            [
                'zgw_zaaktype_url' => $version['url'],
                'name' => $version['omschrijving'] ?? $identificatie,
                'is_active' => true,
            ],
        );

        $urlChanged = $zaaktype->wasRecentlyCreated || $zaaktype->wasChanged('zgw_zaaktype_url');
        $becameActive = ! $zaaktype->wasRecentlyCreated && $zaaktype->wasChanged('is_active');

        // municipality_id is guarded from mass assignment and the role comes from
        // the mapping, so both are set explicitly.
        $changed = false;

        if ($zaaktype->municipality_id !== $municipality->id) {
            $zaaktype->municipality_id = $municipality->id;
            $changed = true;
        }

        if ($role !== null && $zaaktype->role !== $role) {
            $zaaktype->role = $role;
            $changed = true;
        }

        if ($changed) {
            $zaaktype->save();
        }

        return new ZaaktypeRefreshResult(
            ZaaktypeRefreshStatus::Refreshed,
            $zaaktype,
            urlChanged: $urlChanged,
            becameActive: $becameActive,
        );
    }
}
