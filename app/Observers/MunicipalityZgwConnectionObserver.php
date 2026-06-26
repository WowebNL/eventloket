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
    public function saved(MunicipalityZgwConnection $connection): void
    {
        $this->invalidate();
    }

    public function deleted(MunicipalityZgwConnection $connection): void
    {
        $this->invalidate();
    }

    /**
     * Drop the host-index cache (used by webhook reverse-mapping) and restart
     * the workers so both pick up the changed connection.
     */
    private function invalidate(): void
    {
        Cache::forget(ZgwConnectionResolver::HOST_INDEX_CACHE_KEY);
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
