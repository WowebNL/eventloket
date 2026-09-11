<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ResolveZaaktype;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a resolved zaaktype on the ZGW connection the work that uses it runs on.
 *
 * The runtime connection of a municipality falls back to the main connection
 * whenever its own connection cannot be used: it is not activated, or its config
 * cannot be built. That fallback moves the work but not the zaaktype: the
 * resolved row keeps pointing at a zaaktype in the municipality's own catalogus,
 * which the main instance does not know, so the call fails on the receiving side
 * instead of landing on main.
 *
 * So when the connection falls back, the zaaktype falls back with it. That is
 * also exactly what deactivating a connection promises, and what the per-zaaktype
 * fallback in {@see ZaaktypeMainFallback} already does for a zaaktype that lost
 * its valid version.
 *
 * When the main catalogus has no counterpart for this role the own row is kept:
 * the work then fails loudly and traceably on the guard at its own call site
 * instead of using a zaaktype of another instance.
 *
 * Shared on purpose. There are two places that resolve a zaaktype for a
 * municipality and a role -- {@see ResolveZaaktype} for an aanvraag and
 * {@see Municipality::resolveDoorkomstZaaktype()} for a route-passage deelzaak --
 * and a fallback that only one of them applies is the defect this class exists to
 * prevent. See {@see CreateDoorkomstZaken} for the second call site.
 */
final class ZaaktypeConnectionFallback
{
    public function __construct(
        private readonly ZaaktypeMainFallback $mainFallback,
        private readonly ZgwConnectionResolver $connections,
    ) {}

    /**
     * The zaaktype to use for this role, moved to the main catalogus when the
     * municipality's connection falls back to main and a counterpart exists.
     */
    public function follow(Municipality $municipality, ZaaktypeRole $role, Zaaktype $zaaktype): Zaaktype
    {
        // Read via getAttribute(): the column name collides with Eloquent's own
        // $connection property when accessed from model scope.
        $rowConnection = (string) $zaaktype->getAttribute('connection');

        if ($rowConnection === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return $zaaktype;
        }

        $reason = $this->connections->mainFallbackReason($municipality);

        if ($reason === null) {
            return $zaaktype;
        }

        $fallback = $this->mainFallback->activateForRole($municipality, $role);

        Log::warning('ZGW connection falls back to main, so the zaaktype falls back with it.', [
            'municipality_id' => $municipality->id,
            'municipality' => $municipality->name,
            'intended_connection' => $rowConnection,
            'connection' => ZgwConnectionResolver::DEFAULT_CONNECTION,
            'reason' => $reason,
            'role' => $role->value,
            'zaaktype_id' => $zaaktype->id,
            'fallback_zaaktype_id' => $fallback?->id,
        ]);

        return $fallback ?? $zaaktype;
    }
}
