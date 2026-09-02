<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Enums\ZaaktypeRefreshStatus;
use App\Models\Zaaktype;

/**
 * Outcome of refreshing a single local zaaktype row against its catalogus.
 * The transition flags let callers act only on state changes (fallback on
 * becameInactive, restore on becameActive) instead of on every refresh.
 */
final readonly class ZaaktypeRefreshResult
{
    public function __construct(
        public ZaaktypeRefreshStatus $status,
        public ?Zaaktype $zaaktype = null,
        public bool $urlChanged = false,
        public bool $becameActive = false,
        public bool $becameInactive = false,
    ) {}
}
