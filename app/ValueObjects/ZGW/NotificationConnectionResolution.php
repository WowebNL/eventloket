<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

/**
 * The outcome of resolving which ZGW connection an incoming notification belongs
 * to, without contacting any ZGW instance.
 *
 * Three shapes:
 * - decided: exactly one connection owns the notification;
 * - foreign: the notification carries an organisation kenmerk that matches none
 *   of the connections configured for its host, so it is about another
 *   organisation on a shared instance and is not ours to process;
 * - undecided: the host has several possible owners and the notification carries
 *   nothing to tell them apart, so only a read attempt can decide.
 */
final class NotificationConnectionResolution
{
    /**
     * @param  list<string>  $candidates  connection names that have the host configured
     */
    private function __construct(
        public readonly ?string $connection,
        public readonly bool $foreign,
        public readonly array $candidates,
        public readonly ?string $organisatie = null,
    ) {}

    public static function decided(string $connection): self
    {
        return new self($connection, false, [$connection]);
    }

    /**
     * @param  list<string>  $candidates
     */
    public static function foreign(array $candidates, string $organisatie): self
    {
        return new self(null, true, $candidates, $organisatie);
    }

    /**
     * @param  list<string>  $candidates
     */
    public static function undecided(array $candidates): self
    {
        return new self(null, false, $candidates);
    }
}
