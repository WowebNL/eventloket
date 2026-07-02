<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\ZaaktypeKoppelingIssue;
use App\Enums\ZaaktypeRefreshStatus;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Zaaktype;
use App\Notifications\ZaaktypeKoppelingWarning;
use App\ValueObjects\ZGW\BlueprintFinding;
use App\ValueObjects\ZGW\ZaaktypeRefreshResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Orchestrates a targeted zaaktype refresh: syncs the local row, engages or
 * logs the main fallback on availability transitions, clears the caches a
 * version bump invalidates, runs the blueprint health check, and warns the
 * platform admins plus the municipality's koppeling-beheerders.
 *
 * Used by the zaaktypen-kanaal webhook, the koppeling observer, and the
 * app:sync-zaaktypen command, so every refresh path shares the same
 * transition detection and (transition-gated or throttled) notifications.
 */
final class ZaaktypeRefresher
{
    public function __construct(
        private readonly MappedZaaktypeSync $mappedSync,
        private readonly ZaaktypeMainFallback $fallback,
        private readonly ZaaktypeBlueprintHealth $health,
        private readonly ZgwConnectionResolver $resolver,
    ) {}

    /**
     * Refresh a mapped zaaktype of a municipality with its own ZGW instance.
     */
    public function refreshOwnInstance(Municipality $municipality, string $identificatie): ZaaktypeRefreshResult
    {
        $result = $this->mappedSync->refresh($municipality, $identificatie);
        $connectionName = $this->resolver->forManagement($municipality);

        $context = [
            'connection' => $connectionName,
            'municipality_id' => $municipality->id,
            'identificatie' => $identificatie,
        ];

        if ($result->status === ZaaktypeRefreshStatus::Failed) {
            // Already logged by MappedZaaktypeSync; a transient failure must not
            // flip routing or notify anyone.
            return $result;
        }

        if ($result->status === ZaaktypeRefreshStatus::Unavailable) {
            if ($result->becameInactive && $result->zaaktype !== null) {
                $fallbackZaaktype = $this->fallback->activate($municipality, $result->zaaktype);

                Log::warning('Mapped zaaktype has no valid definitief version anymore; falling back to main.', [
                    ...$context,
                    'fallback_zaaktype_id' => $fallbackZaaktype?->id,
                ]);

                $this->notify(new ZaaktypeKoppelingWarning(
                    $result->zaaktype,
                    ZaaktypeKoppelingIssue::Unavailable,
                    $municipality,
                    fallbackZaaktypeName: $fallbackZaaktype?->name,
                ), $municipality);
            } else {
                Log::debug('Zaaktype is still unavailable; nothing changed.', $context);
            }

            return $result;
        }

        if ($result->becameActive && $result->zaaktype !== null) {
            Log::info('Zaaktype has a valid definitief version again; own-instance row reactivated.', $context);

            $this->notify(new ZaaktypeKoppelingWarning(
                $result->zaaktype,
                ZaaktypeKoppelingIssue::Restored,
                $municipality,
            ), $municipality);
        }

        if ($result->urlChanged && $result->zaaktype !== null) {
            $this->clearVersionCaches($connectionName, $municipality->id);

            Log::info('Zaaktype refreshed to a new version.', [
                ...$context,
                'zaaktype_url' => $result->zaaktype->zgw_zaaktype_url,
            ]);

            $mapping = MunicipalityZaaktypeMapping::query()
                ->where('municipality_id', $municipality->id)
                ->where('zaaktype_identificatie', $identificatie)
                ->first();

            $this->checkBlueprint($result->zaaktype, $connectionName, $identificatie, $mapping, $municipality);
        }

        if (! $result->becameActive && ! $result->urlChanged) {
            Log::debug('Zaaktype notification produced no changes.', $context);
        }

        return $result;
    }

    /**
     * Refresh an existing main-catalogus zaaktype row. Main has no fallback (it
     * is the fallback), so an unavailable zaaktype is deactivated and unlinked,
     * exactly like the next SyncZaaktypen run would.
     */
    public function refreshMain(string $identificatie): void
    {
        $connectionName = ZgwConnectionResolver::DEFAULT_CONNECTION;

        $zaaktype = Zaaktype::query()
            ->where('connection', $connectionName)
            ->where('identificatie', $identificatie)
            ->first();

        $context = ['connection' => $connectionName, 'identificatie' => $identificatie];

        if ($zaaktype === null) {
            Log::debug('Zaaktype notification for an unknown main identificatie ignored.', $context);

            return;
        }

        try {
            $version = ZaaktypeVersion::currentDefinitief($connectionName, $identificatie);
        } catch (Throwable $e) {
            Log::warning('Could not read the main catalogus for a zaaktype refresh.', [
                ...$context,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        if ($version === null) {
            $this->deactivateMain($zaaktype, $context);

            return;
        }

        $municipality = $zaaktype->municipality;

        $zaaktype->fill([
            'zgw_zaaktype_url' => $version['url'],
            'name' => $version['omschrijving'] ?? $identificatie,
            'is_active' => true,
        ]);

        $urlChanged = $zaaktype->isDirty('zgw_zaaktype_url');
        $zaaktype->save();

        if (! $urlChanged) {
            Log::debug('Zaaktype notification produced no changes.', $context);

            return;
        }

        $this->clearVersionCaches($connectionName, $municipality?->id);

        Log::info('Zaaktype refreshed to a new version.', [
            ...$context,
            'zaaktype_url' => $zaaktype->zgw_zaaktype_url,
        ]);

        if ($municipality !== null) {
            // Main zaaktypen have no koppeling mapping; the health check runs on
            // the heuristics alone.
            $this->checkBlueprint($zaaktype, $connectionName, $identificatie, null, $municipality);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deactivateMain(Zaaktype $zaaktype, array $context): void
    {
        if (! $zaaktype->is_active) {
            Log::debug('Zaaktype is still unavailable; nothing changed.', $context);

            return;
        }

        $municipality = $zaaktype->municipality;

        $zaaktype->is_active = false;
        $zaaktype->municipality_id = null;
        $zaaktype->save();

        Log::warning('Main zaaktype has no valid definitief version anymore; row deactivated.', [
            ...$context,
            'municipality_id' => $municipality?->id,
        ]);

        $this->notify(new ZaaktypeKoppelingWarning(
            $zaaktype,
            ZaaktypeKoppelingIssue::MainUnavailable,
            $municipality,
        ), $municipality);
    }

    private function checkBlueprint(Zaaktype $zaaktype, string $connectionName, string $identificatie, ?MunicipalityZaaktypeMapping $mapping, Municipality $municipality): void
    {
        $findings = $this->health->check($connectionName, $identificatie, $mapping);

        if ($findings === []) {
            return;
        }

        Log::warning('Zaaktype version misses blueprint prerequisites.', [
            'connection' => $connectionName,
            'municipality_id' => $municipality->id,
            'identificatie' => $identificatie,
            'findings' => array_map(fn (BlueprintFinding $finding) => $finding->key(), $findings),
        ]);

        // Suppress repeats of the same unresolved finding set for a day; a
        // changed set notifies immediately.
        $keys = array_map(fn (BlueprintFinding $finding) => $finding->key(), $findings);
        sort($keys);
        $cacheKey = 'zaaktype_blueprint_warning:'.$connectionName.':'.$identificatie.':'.md5(implode('|', $keys));

        if (! Cache::add($cacheKey, true, now()->addDay())) {
            return;
        }

        $this->notify(new ZaaktypeKoppelingWarning(
            $zaaktype,
            ZaaktypeKoppelingIssue::BlueprintIncomplete,
            $municipality,
            findings: $findings,
        ), $municipality);
    }

    /**
     * Forget the caches that hold pre-publish data after a version bump. The
     * url-keyed zaaktype caches self-heal because the row's url advanced.
     */
    private function clearVersionCaches(string $connectionName, ?int $municipalityId): void
    {
        Cache::forget("statustypen.v2.{$connectionName}");
        Cache::forget('zaak_status_name_options_global');

        if ($municipalityId !== null) {
            Cache::forget("zaak_status_name_options_{$municipalityId}");
        }
    }

    private function notify(ZaaktypeKoppelingWarning $notification, ?Municipality $municipality): void
    {
        /** @var Collection<int, User> $recipients */
        $recipients = AdminUser::all()->toBase();

        if ($municipality !== null) {
            $recipients = $recipients->merge($municipality->municipalityBeheerderUsers()->get()->toBase());
        }

        Notification::send($recipients->unique('id')->values(), $notification);
    }
}
