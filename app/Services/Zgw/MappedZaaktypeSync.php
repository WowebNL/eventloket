<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

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
        if ($identificatie === '') {
            return null;
        }

        // Resolving registers the per-municipality connection config. This is a
        // management sync, so it uses the connection regardless of activation (a
        // koppeling is configured before going live). An invalid or absent config
        // falls back to "main", which this targeted sync skips: main rows are
        // linked by name in SyncZaaktypen, not created here.
        $connectionName = $this->resolver->forManagement($municipality);

        if ($connectionName === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return null;
        }

        try {
            $version = $this->definitiefVersion($connectionName, $identificatie);
        } catch (Throwable $e) {
            Log::warning('MappedZaaktypeSync: kon zaaktype niet ophalen', [
                'municipality_id' => $municipality->id,
                'identificatie' => $identificatie,
                'connection' => $connectionName,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if ($version === null) {
            return null;
        }

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

        return $zaaktype;
    }

    /**
     * The current definitief version of an identificatie: the one valid today,
     * falling back to any definitief version. Mirrors the resolution used by
     * {@see ZaaktypeCatalogusOptions}.
     *
     * @return array<string, mixed>|null
     */
    private function definitiefVersion(string $connectionName, string $identificatie): ?array
    {
        $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
            'identificatie' => $identificatie,
            'status' => 'definitief',
            'datumGeldigheid' => now('Europe/Amsterdam')->toDateString(),
        ])->first()
            ?? Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                'identificatie' => $identificatie,
                'status' => 'definitief',
            ])->first();

        if (! is_array($version) || ! is_string($version['url'] ?? null) || $version['url'] === '') {
            return null;
        }

        return $version;
    }
}
