<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Illuminate\Support\Carbon;

/**
 * The structured result of {@see AbonnementHealthCheck::run()}: the status plus
 * the details a caller needs to explain it (which channels are missing, when the
 * token expires).
 */
final readonly class AbonnementCheckResult
{
    /**
     * @param  list<string>  $missingKanalen
     */
    public function __construct(
        public AbonnementCheckStatus $status,
        public array $missingKanalen = [],
        public ?Carbon $expiresAt = null,
    ) {}
}
