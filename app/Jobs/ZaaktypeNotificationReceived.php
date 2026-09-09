<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\UnresolvedNotificationConnectionException;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\Models\ZgwRequestLog;
use App\Services\Zgw\NotificationResourceReader;
use App\Services\Zgw\ZaaktypeRefresher;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\OpenNotification;
use App\ValueObjects\ZGW\NotificationResource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;

/**
 * Handles a zaaktypen-kanaal notification by refreshing the local zaaktype row
 * it concerns. The notification only carries the zaaktype version url
 * (hoofdObject), so the identificatie is read from the catalogus; on a destroy
 * the fetch 404s and the local rows are matched by url instead.
 *
 * Publishing a zaaktype fires a burst of notifications (the zaaktype update
 * plus child-resource creates, all sharing the same hoofdObject). The dispatch
 * delay in {@see ProcessOpenNotification} combined with ShouldBeUnique
 * collapses that burst into a single refresh; the refresh itself is
 * idempotent, so a late straggler after the unique window just no-ops.
 */
class ZaaktypeNotificationReceived implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** Safely exceeds the dispatch delay plus retries. */
    public int $uniqueFor = 600;

    public function __construct(private OpenNotification $notification) {}

    public function uniqueId(): string
    {
        return 'zaaktype-notification:'.md5($this->notification->hoofdObject);
    }

    public function handle(ZaaktypeRefresher $refresher): void
    {
        $reader = app(NotificationResourceReader::class);

        // The zaaktypen channel carries no organisation kenmerk, so on an
        // instance shared by several connections the local zaaktype row is what
        // identifies the owner; a version we do not know yet falls through to a
        // read attempt per candidate.
        try {
            $resource = $reader->resolve($this->notification);
        } catch (UnresolvedNotificationConnectionException $e) {
            if ($e->allCandidatesReportGone()) {
                // The version is gone everywhere and matches no local row: a
                // destroy of a zaaktype we never referenced.
                Log::debug('Zaaktype notification for a version that no longer exists ignored.', $this->context(ZgwConnectionResolver::DEFAULT_CONNECTION, null));

                return;
            }

            throw $e;
        }

        if ($resource === null) {
            return;
        }

        $connectionName = $resource->connection;

        $identificatie = $this->resolveIdentificatie($resource, $reader);

        $municipalityId = ZgwRequestLog::municipalityIdFromConnection($connectionName);

        if ($municipalityId === null) {
            $this->refreshMain($refresher, $identificatie);

            return;
        }

        $municipality = Municipality::find($municipalityId);

        if ($municipality === null) {
            Log::debug('Zaaktype notification for an unknown municipality ignored.', $this->context($connectionName, $identificatie));

            return;
        }

        $this->refreshOwnInstance($refresher, $municipality, $connectionName, $identificatie);
    }

    /**
     * The identificatie of the zaaktype the notification concerns: fetched from
     * the catalogus, or matched against local rows when the version is already
     * gone (destroy). Null when neither resolves.
     */
    private function resolveIdentificatie(NotificationResource $resource, NotificationResourceReader $reader): ?string
    {
        try {
            $identificatie = $reader->read($resource, $this->notification)['identificatie'] ?? null;

            return is_string($identificatie) && $identificatie !== '' ? $identificatie : null;
        } catch (Throwable $e) {
            if (! $this->resourceIsGone($e)) {
                // Transient error: rethrow for a retry; exhausting the retries
                // surfaces through the failed-jobs channel.
                throw $e;
            }
        }

        return Zaaktype::query()
            ->where('connection', $resource->connection)
            ->where('zgw_zaaktype_url', $this->notification->hoofdObject)
            ->value('identificatie');
    }

    private function refreshMain(ZaaktypeRefresher $refresher, ?string $identificatie): void
    {
        if ($identificatie === null) {
            // A destroyed version we never referenced: nothing to refresh.
            Log::debug('Zaaktype notification without a resolvable identificatie ignored.', $this->context(ZgwConnectionResolver::DEFAULT_CONNECTION, null));

            return;
        }

        $refresher->refreshMain($identificatie);
    }

    private function refreshOwnInstance(ZaaktypeRefresher $refresher, Municipality $municipality, string $connectionName, ?string $identificatie): void
    {
        $mapped = MunicipalityZaaktypeMapping::query()
            ->where('municipality_id', $municipality->id)
            ->whereNotNull('zaaktype_identificatie')
            ->distinct()
            ->pluck('zaaktype_identificatie');

        if ($identificatie !== null) {
            // Only mapped zaaktypen can carry aanvragen; the rest of an external
            // catalogus is none of our concern (mirrors MappedZaaktypeSync).
            if (! $mapped->contains($identificatie)) {
                Log::debug('Zaaktype notification for an unmapped identificatie ignored.', $this->context($connectionName, $identificatie));

                return;
            }

            $refresher->refreshOwnInstance($municipality, $identificatie);

            return;
        }

        // A destroy without a local url match may still have hit the mapped
        // zaaktype's latest version; re-verify every mapped identificatie.
        foreach ($mapped as $mappedIdentificatie) {
            $refresher->refreshOwnInstance($municipality, $mappedIdentificatie);
        }
    }

    /**
     * Whether the exception means the resource no longer exists (destroy), as
     * opposed to a transient transport or server error.
     */
    private function resourceIsGone(Throwable $e): bool
    {
        $status = match (true) {
            $e instanceof ApiRequestException => $e->getResponse()->status(),
            $e instanceof RequestException => $e->response->status(),
            default => null,
        };

        return in_array($status, [404, 410], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(string $connectionName, ?string $identificatie): array
    {
        return [
            'connection' => $connectionName,
            'identificatie' => $identificatie,
            'actie' => $this->notification->actie,
            'resource' => $this->notification->resource,
            'url' => $this->notification->hoofdObject,
        ];
    }
}
