<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Console\Commands\Zaaktypen\SyncZaaktypen;
use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ResolveZaaktype;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;

/**
 * Activates the per-zaaktype fallback to the main connection: when a mapped
 * own-instance zaaktype has no valid definitief version anymore, the matching
 * main-catalogus zaaktype is linked to the municipality so
 * {@see ResolveZaaktype} finds it by role and new zaken
 * are created on main.
 *
 * Invariant this creates: a main row linked to a municipality with an own ZGW
 * connection is an (active or historical) fallback. That link is never removed
 * on restore, because zaken created during the fallback window derive their
 * municipality through the zaaktype row ({@see Zaak::municipality()});
 * the revived own-instance row simply wins again through the resolve ordering.
 * {@see SyncZaaktypen} preserves the link too:
 * it skips own-instance municipalities when linking and only unlinks inactive
 * rows. Connection resolution stays correct because the resolver honours the
 * row's `connection` column.
 */
final class ZaaktypeMainFallback
{
    /**
     * Link the matching active main-catalogus zaaktype (same role, name ending
     * in "gemeente {name}") to the municipality. Returns the linked row, or
     * null when the main catalogus has no candidate for this role.
     */
    public function activate(Municipality $municipality, Zaaktype $ownZaaktype): ?Zaaktype
    {
        if ($ownZaaktype->role === null) {
            return null;
        }

        return $this->activateForRole($municipality, $ownZaaktype->role);
    }

    /**
     * Link the matching active main-catalogus zaaktype (same role, name ending
     * in "gemeente {name}") to the municipality. Returns the linked row, or
     * null when the main catalogus has no candidate for this role.
     *
     * Used both by the fallback on an unavailable own-instance zaaktype and by
     * {@see ResolveZaaktype} when an own-instance municipality never coupled a
     * role at all, so an aanvraag for that role is still created on main.
     */
    public function activateForRole(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        $candidates = Zaaktype::query()
            ->where('connection', ZgwConnectionResolver::DEFAULT_CONNECTION)
            ->where('is_active', true)
            ->where('role', $role->value)
            ->where(function ($query) use ($municipality) {
                // A main row may already be linked to this municipality from
                // before it switched to its own instance (legacy links survive).
                $query->whereNull('municipality_id')->orWhere('municipality_id', $municipality->id);
            })
            ->get();

        // Match on the exact municipality-name suffix (the same convention
        // SyncZaaktypen::syncMunicipalityLinks parses), not on LIKE: a prefix
        // match would conflate "Sittard" and "Sittard-Geleen".
        $fallback = $candidates->first(function (Zaaktype $candidate) use ($municipality) {
            if (! preg_match('/\bgemeente\s+(.+)$/iu', $candidate->name, $matches)) {
                return false;
            }

            return strtolower(trim($matches[1])) === strtolower($municipality->name);
        });

        if ($fallback === null) {
            return null;
        }

        if ($fallback->municipality_id !== $municipality->id) {
            $fallback->municipality_id = $municipality->id;
            $fallback->save();
        }

        return $fallback;
    }
}
