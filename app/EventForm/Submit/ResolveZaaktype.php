<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
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
 */
final class ResolveZaaktype
{
    public function __construct(private readonly DetermineAanvraagType $determineAanvraagType) {}

    public function forState(FormState $state): Zaaktype
    {
        $municipality = $this->resolveMunicipality($state);
        $role = $this->determineAanvraagType->forState($state);

        $zaaktype = $this->resolveByMapping($municipality, $role)
            ?? $this->resolveByRole($municipality, $role)
            ?? $this->resolveByNamePrefix($municipality, $role);

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
            $muni = Municipality::where('brk_identification', $brk)->first();
            if ($muni) {
                return $muni;
            }
        }

        throw new RuntimeException('Geen gemeente herleidbaar uit de FormState (evenementInGemeente.brk_identification ontbreekt of matcht niets).');
    }
}
