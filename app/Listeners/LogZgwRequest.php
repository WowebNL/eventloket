<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\ZgwRequestLog;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Events\ZgwRequestSent;

/**
 * Persists a metadata row for every ZGW request. Bodies are never stored and
 * the URI is reduced to its path, because ZGW query filters can carry personal
 * data. A logging failure must never break the originating request.
 */
class LogZgwRequest
{
    public function handle(ZgwRequestSent $event): void
    {
        try {
            ZgwRequestLog::create([
                'connection' => $event->connection,
                'municipality_id' => ZgwRequestLog::municipalityIdFromConnection($event->connection),
                // Present for panel-initiated calls; null for queued/console traffic.
                'user_id' => auth()->id(),
                'method' => $event->method,
                'resource' => $this->path($event->uri),
                'status_code' => $event->status,
                'failed' => $event->status >= 400,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record a ZGW request log.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * The URI path without its query string (which may contain personal data).
     */
    private function path(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $uri;
    }
}
