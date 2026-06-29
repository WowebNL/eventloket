<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwAbonnement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Drives the notification round-trip check: publish a notification on a channel
 * the abonnement already subscribes to and let the caller wait for it to arrive
 * back on our public callback. A correlation id (in kenmerken) ties the
 * published notification to the receipt, so only a notification we triggered
 * counts; the callback recognises it by that id, not by the channel.
 *
 * This only succeeds when our callback URL is publicly reachable from the
 * Notificaties API, so it cannot work in local development.
 */
class NotificationRoundTripProbe
{
    private const PENDING_PREFIX = 'zgw_notif_probe.pending.';

    private const RECEIVED_PREFIX = 'zgw_notif_probe.received.';

    /** How long the pending/received markers live, in seconds. */
    private const TTL_SECONDS = 120;

    /**
     * The channel the probe publishes on. It must be one the abonnement is
     * subscribed to ({@see AbonnementRegistrar::KANALEN}); the callback short-
     * circuits on the correlation id before any real processing happens.
     */
    private const KANAAL = 'zaken';

    /**
     * Publish a test notification and return its correlation id. The caller polls
     * {@see hasReceived()} until the callback records the receipt (or it times
     * out). Throws when the notification cannot be published.
     */
    public function start(string $connectionName): string
    {
        $api = new NotificatiesApi($connectionName);
        $baseUrl = $api->baseUrl();

        $probeId = (string) Str::uuid();
        self::markPending($probeId, $connectionName);

        $reference = $this->reference($connectionName, $baseUrl);

        try {
            $api->publish([
                'kanaal' => self::KANAAL,
                'resource' => 'test',
                'hoofdObject' => $reference,
                'resourceUrl' => $reference,
                'actie' => 'create',
                'aanmaakdatum' => now()->toIso8601String(),
                'kenmerken' => ['probe_id' => $probeId],
            ]);
        } catch (\Throwable $e) {
            Cache::forget(self::PENDING_PREFIX.$probeId);

            throw $e;
        }

        return $probeId;
    }

    /**
     * Mark a probe id as awaiting a callback receipt.
     */
    public static function markPending(string $probeId, string $connectionName): void
    {
        Cache::put(self::PENDING_PREFIX.$probeId, $connectionName, self::TTL_SECONDS);
    }

    /**
     * Record that a test notification carrying this probe id arrived on the
     * callback. Returns false when the id is unknown (not one we triggered), so
     * the callback can safely ignore unsolicited test notifications.
     */
    public static function recordReceipt(string $probeId): bool
    {
        if (Cache::pull(self::PENDING_PREFIX.$probeId) === null) {
            return false;
        }

        Cache::put(self::RECEIVED_PREFIX.$probeId, true, self::TTL_SECONDS);

        return true;
    }

    /**
     * Whether a receipt for this probe id has been recorded (peek, non-consuming).
     */
    public static function hasReceived(string $probeId): bool
    {
        return Cache::has(self::RECEIVED_PREFIX.$probeId);
    }

    /**
     * The reference URL to put on the test notification: the registered
     * abonnement url when known, otherwise the notificaties base url. Its only
     * purpose is to be a valid on-host URL; the callback matches on the
     * correlation id, not this value.
     */
    private function reference(string $connectionName, string $baseUrl): string
    {
        $abonnementUrl = ZgwAbonnement::query()
            ->where('connection', $connectionName)
            ->value('abonnement_url');

        return is_string($abonnementUrl) && $abonnementUrl !== '' ? $abonnementUrl : $baseUrl;
    }
}
