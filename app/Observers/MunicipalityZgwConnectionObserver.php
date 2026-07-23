<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MunicipalityZgwConnection;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps long-lived queue workers in sync with connection changes.
 *
 * Web requests are short-lived and always read the current connection from the
 * database, but Horizon workers memoise the resolved connection (and the package
 * caches the built connection per name) for their whole lifetime. When a
 * connection is created, changed or removed we gracefully terminate Horizon so
 * the workers restart and pick up the new configuration on their next job.
 */
class MunicipalityZgwConnectionObserver
{
    public function saving(MunicipalityZgwConnection $connection): void
    {
        $this->deactivateOnCriticalChange($connection);
    }

    public function saved(MunicipalityZgwConnection $connection): void
    {
        $this->auditSecretRotation($connection);
        $this->invalidate();
    }

    /**
     * A change to an endpoint, credential or the ZGW version means the
     * connection now points at (or authenticates against) something that has not
     * been verified. Take it offline until it is re-verified and re-activated by
     * clearing both stamps in the same write. Cosmetic changes (tab toggles,
     * vertrouwelijkheid map) leave an activated connection live.
     *
     * The activate/deactivate actions only touch activated_at, and the
     * ConnectionVerifier stamps last_verified_at with updateQuietly (which does
     * not fire observers), so neither path is affected here.
     */
    private function deactivateOnCriticalChange(MunicipalityZgwConnection $connection): void
    {
        if (! $connection->exists) {
            return;
        }

        if (! $connection->isDirty(MunicipalityZgwConnection::CONNECTION_CRITICAL_FIELDS)) {
            return;
        }

        $connection->activated_at = null;
        $connection->last_verified_at = null;
    }

    public function deleted(MunicipalityZgwConnection $connection): void
    {
        $this->invalidate();
    }

    /**
     * The LogsActivity trait excludes the client secret from its field log, so a
     * secret-only change would otherwise leave no trail. Record that the secret
     * changed (never its value) as a separate, redacted audit entry.
     */
    private function auditSecretRotation(MunicipalityZgwConnection $connection): void
    {
        if (! $connection->wasChanged('client_secret')) {
            return;
        }

        try {
            activity()
                ->performedOn($connection)
                ->causedBy(auth()->user())
                ->event('secret_rotated')
                ->withProperties(['municipality_id' => $connection->municipality_id])
                ->log('ZGW client secret rotated.');
        } catch (Throwable $e) {
            Log::warning('Could not record a ZGW client secret rotation.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drop the host-index cache (used by webhook reverse-mapping) and restart
     * the workers so both pick up the changed connection.
     */
    private function invalidate(): void
    {
        Cache::forget(ZgwConnectionResolver::HOST_INDEX_CACHE_KEY);
        Cache::forget(ZgwConnectionResolver::ALLOWED_HOSTS_CACHE_KEY);
        $this->restartWorkers();
    }

    /**
     * Signal Horizon to finish in-flight jobs and restart. Failure here must
     * never break the connection write, so it is logged and swallowed.
     */
    private function restartWorkers(): void
    {
        try {
            Artisan::call('horizon:terminate');
        } catch (Throwable $e) {
            Log::warning('Could not terminate Horizon after a ZGW connection change.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
