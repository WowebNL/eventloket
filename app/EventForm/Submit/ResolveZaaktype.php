<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\Exceptions\GemeenteLocatieMismatchException;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaaktypeMainFallback;
use Illuminate\Database\Eloquent\Builder;
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
 */
final class ResolveZaaktype
{
    public function __construct(
        private readonly DetermineAanvraagType $determineAanvraagType,
        private readonly ZaaktypeMainFallback $mainFallback,
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

        return $zaaktype;
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
