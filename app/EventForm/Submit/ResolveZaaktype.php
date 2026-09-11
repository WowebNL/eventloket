<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\CreateZaakInZGW;
use App\Exceptions\GemeenteLocatieMismatchException;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaaktypeMainFallback;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Zoekt het juiste `Zaaktype` voor een submit, op basis van de
 * (gemeente × rol)-combinatie.
 *
 * Primair pad: de per-gemeente blueprint (`MunicipalityZaaktypeMapping`)
 * koppelt de rol aan een logische `Zaaktype.identificatie`.
 *
 * Daarna: de expliciete `role`-kolom op het `Zaaktype` (door de admin gezet of
 * door `SyncZaaktypen` uit de naam-prefix afgeleid).
 *
 * Laatste terugval (legacy): de naamconventie zoals `SyncZaaktypen` die ook
 * gebruikt om zaaktypes aan een gemeente te koppelen, bv:
 *
 *   "Evenementenvergunning gemeente Heerlen"
 *   "Melding evenement gemeente Maastricht"
 *   "Vooraankondiging gemeente Sittard-Geleen"
 *
 * Allerlaatste terugval (alleen bij een eigen-instantie-gemeente): heeft die
 * gemeente deze rol niet gekoppeld, dan valt de aanvraag terug op het main-
 * zaaktype, zodat de zaak toch wordt aangemaakt. Zie {@see resolveMainFallback}.
 *
 * Over al deze routes heen geldt tot slot dat het gekozen zaaktype op dezelfde
 * ZGW-koppeling moet liggen als de zaak die ermee wordt aangemaakt. Valt die
 * koppeling terug op main, dan valt het zaaktype mee terug; zie
 * {@see followConnectionFallback}.
 */
final class ResolveZaaktype
{
    public function __construct(
        private readonly DetermineAanvraagType $determineAanvraagType,
        private readonly ZaaktypeMainFallback $mainFallback,
        private readonly ZgwConnectionResolver $connections,
    ) {}

    public function forState(FormState $state): Zaaktype
    {
        $municipality = $this->resolveMunicipality($state);
        $role = $this->determineAanvraagType->forState($state);

        $zaaktype = $this->resolveByMapping($municipality, $role)
            ?? $this->resolveByRole($municipality, $role)
            ?? $this->resolveByNamePrefix($municipality, $role)
            ?? $this->resolveMainFallback($municipality, $role);

        if (! $zaaktype) {
            throw new RuntimeException(sprintf(
                'Geen actief zaaktype gevonden voor gemeente "%s" met rol "%s".',
                $municipality->name,
                $role->value,
            ));
        }

        return $this->followConnectionFallback($municipality, $role, $zaaktype);
    }

    /**
     * Keep the zaaktype on the connection the zaak will actually be created on.
     *
     * The runtime connection of a municipality falls back to the main connection
     * whenever its own connection cannot be used: it is not activated, or its
     * config cannot be built. That fallback moves the zaak but not the zaaktype:
     * the resolved row keeps pointing at a zaaktype in the municipality's own
     * catalogus, which the main instance does not know, so the submit fails on
     * the receiving side instead of landing on main.
     *
     * So when the connection falls back, the zaaktype falls back with it. That is
     * also exactly what deactivating a connection promises, and what the
     * per-zaaktype fallback in {@see ZaaktypeMainFallback} already does for a
     * zaaktype that lost its valid version.
     *
     * When the main catalogus has no counterpart for this role the own row is
     * kept: the submit then fails loudly and traceably on the guard in
     * {@see CreateZaakInZGW} instead of creating a
     * zaak against a zaaktype of another instance.
     */
    private function followConnectionFallback(Municipality $municipality, ZaaktypeRole $role, Zaaktype $zaaktype): Zaaktype
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

    private function resolveByMapping(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        $mapping = MunicipalityZaaktypeMapping::forMunicipalityRole($municipality, $role);
        if (! $mapping || ! $mapping->zaaktype_identificatie) {
            return null;
        }

        return Zaaktype::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->where('identificatie', $mapping->zaaktype_identificatie)
            ->first();
    }

    private function resolveByRole(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        return $this->preferOwnConnection(
            Zaaktype::query()
                ->where('municipality_id', $municipality->id)
                ->where('is_active', true)
                ->where('role', $role->value),
        )->first();
    }

    private function resolveByNamePrefix(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        return $this->preferOwnConnection(
            Zaaktype::query()
                ->where('municipality_id', $municipality->id)
                ->where('is_active', true)
                ->where('name', 'like', $role->namePrefix().'%'),
        )->first();
    }

    /**
     * Final fallback for a municipality that runs its own ZGW instance but never
     * coupled this role (no mapping and no own-instance row). Link and use the
     * matching main-catalogus zaaktype so the aanvraag is still created on the
     * main connection instead of failing.
     *
     * Only own-instance municipalities need this: a municipality without an own
     * instance already has its main rows linked by SyncZaaktypen and is resolved
     * by the steps above. Linking here mirrors {@see ZaaktypeMainFallback}, so a
     * zaak created on the fallback derives its municipality through the zaaktype.
     */
    private function resolveMainFallback(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        if (! $municipality->zgwConnection()->exists()) {
            return null;
        }

        return $this->mainFallback->activateForRole($municipality, $role);
    }

    /**
     * During a main-fallback both the (inactive) own-instance row and the linked
     * main row can exist for a municipality; once the own row is active again it
     * must win deterministically over the still-linked main fallback row.
     *
     * @param  Builder<Zaaktype>  $query
     * @return Builder<Zaaktype>
     */
    private function preferOwnConnection(Builder $query): Builder
    {
        return $query->orderByRaw("case when connection = 'main' then 1 else 0 end");
    }

    private function resolveMunicipality(FormState $state): Municipality
    {
        $brk = $state->get('evenementInGemeente.brk_identification');
        if (is_string($brk) && $brk !== '') {
            $this->assertMunicipalityMatchesLocation($state, $brk);

            $muni = Municipality::where('brk_identification', $brk)->first();
            if ($muni) {
                return $muni;
            }
        }

        throw new RuntimeException('Geen gemeente herleidbaar uit de FormState (evenementInGemeente.brk_identification ontbreekt of matcht niets).');
    }

    /**
     * Guards the invariant that the municipality a zaak is created for is one of
     * the municipalities the current location actually falls in. The location
     * check result is authoritative here: it is recomputed on the location gate
     * from the submitted addresses, areas and routes.
     *
     * Without this a stale gemeente in the state (a copied aanvraag, an edited
     * location) would silently create the zaak for the previous municipality,
     * and with it on the previous municipality's ZGW instance. Failing the
     * submit is the lesser harm. A state without a location check result (older
     * drafts) is left alone.
     *
     * @throws GemeenteLocatieMismatchException so the submit handler can tell
     *                                          the organiser to revisit the
     *                                          location step, instead of the
     *                                          generic "try again" that a plain
     *                                          failure produces.
     */
    private function assertMunicipalityMatchesLocation(FormState $state, string $brk): void
    {
        $gemeenten = $state->get('inGemeentenResponse.all.object');
        if (! is_array($gemeenten) || $gemeenten === []) {
            return;
        }

        if (! array_key_exists($brk, $gemeenten)) {
            throw new GemeenteLocatieMismatchException($brk, array_map(strval(...), array_keys($gemeenten)));
        }
    }
}
