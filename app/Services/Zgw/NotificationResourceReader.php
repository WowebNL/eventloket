<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Exceptions\UnresolvedNotificationConnectionException;
use App\ValueObjects\OpenNotification;
use App\ValueObjects\ZGW\NotificationResource;
use Illuminate\Support\Facades\Log;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;

/**
 * Decides which ZGW connection an incoming notification belongs to and reads its
 * hoofdObject with that connection's credentials.
 *
 * {@see ZgwConnectionResolver::forNotification()} does the deciding without any
 * network traffic. This adds the last resort for the case it cannot decide: when
 * several connections are configured against the same instance and the
 * notification carries no organisation kenmerk, each candidate is asked for the
 * resource in turn and the one that may read it is the owner.
 *
 * Every candidate already has the host in its own allowlist, so probing never
 * sends a token anywhere the connection was not configured to talk to.
 */
class NotificationResourceReader
{
    /**
     * Statuses that mean "this candidate is not the owner, try the next one".
     *
     * Which of these a ZGW instance returns for a read with another tenant's
     * credentials differs per implementation (an authorisation failure may show
     * up as 401, 403 or as a plain 404), so all three are treated as a rejection.
     * Anything else (5xx, a timeout) is transient and is rethrown so the job
     * retries instead of silently attributing the resource to another candidate.
     */
    private const REJECTION_STATUSES = [401, 403, 404];

    public function __construct(private readonly ZgwConnectionResolver $connections) {}

    /**
     * The connection that owns the notification, or null when the notification
     * is about another organisation on a shared instance and is not ours to
     * process.
     *
     * @throws UnresolvedNotificationConnectionException when nothing identifies the owner
     */
    public function resolve(OpenNotification $notification): ?NotificationResource
    {
        $resolution = $this->connections->forNotification($notification);

        if ($resolution->foreign) {
            Log::info('ZGW notification for an organisation we do not serve on this instance ignored.', [
                'kanaal' => $notification->kanaal,
                'resource' => $notification->resource,
                'actie' => $notification->actie,
                'organisatie' => $resolution->organisatie,
                'candidates' => $resolution->candidates,
            ]);

            return null;
        }

        if ($resolution->connection !== null) {
            return new NotificationResource($resolution->connection);
        }

        return $this->probe($notification, $resolution->candidates);
    }

    /**
     * The notification's hoofdObject, read with the resolved connection. Reuses
     * the body a probe already fetched instead of asking for it twice.
     *
     * @return array<string, mixed>
     */
    public function read(NotificationResource $resource, OpenNotification $notification): array
    {
        if ($resource->payload !== null) {
            return $resource->payload;
        }

        try {
            return ZgwResource::byUrl($resource->connection, $notification->hoofdObject);
        } catch (DisallowedHostException $e) {
            // The resolved connection does not trust the host the notification
            // points at. Log which connection was picked before rethrowing: the
            // exception itself only names the origin, which is not enough to see
            // why this connection was chosen.
            Log::warning('A ZGW notification resolved to a connection that does not allow its host.', [
                'connection' => $resource->connection,
                'kanaal' => $notification->kanaal,
                'resource' => $notification->resource,
                'host' => parse_url($notification->hoofdObject, PHP_URL_HOST),
            ]);

            throw $e;
        }
    }

    /**
     * Try each candidate in turn; the first one allowed to read the resource owns
     * it.
     *
     * @param  list<string>  $candidates
     *
     * @throws UnresolvedNotificationConnectionException
     */
    private function probe(OpenNotification $notification, array $candidates): NotificationResource
    {
        /** @var array<string, int|null> $attempts */
        $attempts = [];

        foreach ($candidates as $candidate) {
            try {
                return new NotificationResource(
                    $candidate,
                    ZgwResource::byUrl($candidate, $notification->hoofdObject),
                );
            } catch (ApiRequestException $e) {
                $status = $e->getResponse()->status();

                if (! in_array($status, self::REJECTION_STATUSES, true)) {
                    throw $e;
                }

                $attempts[$candidate] = $status;
            } catch (DisallowedHostException) {
                // A candidate whose allowlist rejects the url cannot be the
                // owner; it is not a reason to fail the whole notification.
                $attempts[$candidate] = null;
            }
        }

        throw new UnresolvedNotificationConnectionException(
            sprintf(
                'No configured ZGW connection could read the %s notification for host [%s]; tried %s.',
                $notification->kanaal,
                (string) parse_url($notification->hoofdObject, PHP_URL_HOST),
                $this->describe($attempts),
            ),
            $attempts,
        );
    }

    /**
     * @param  array<string, int|null>  $attempts
     */
    private function describe(array $attempts): string
    {
        $described = [];

        foreach ($attempts as $connection => $status) {
            $described[] = $connection.': '.($status === null ? 'host not allowed' : (string) $status);
        }

        return $described === [] ? 'no candidates' : implode(', ', $described);
    }
}
