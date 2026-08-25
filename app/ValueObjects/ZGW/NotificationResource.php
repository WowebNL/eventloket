<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

/**
 * The connection an incoming notification belongs to, plus the hoofdObject as
 * already read from it when a read attempt was what identified the connection.
 *
 * Handlers use {@see payload} when it is set instead of fetching the same
 * resource a second time.
 */
final class NotificationResource
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public readonly string $connection,
        public readonly ?array $payload = null,
    ) {}
}
