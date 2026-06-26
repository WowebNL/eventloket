<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use RuntimeException;

/**
 * Zoekt het juiste `Zaaktype` voor een submit, op basis van de
 * (gemeente × rol)-combinatie.
 *
 * Primair pad: de per-gemeente blueprint (`MunicipalityZaaktypeMapping`)
 * koppelt de rol aan een logische `Zaaktype.identificatie`.
 *
 * Fallback (geen mapping): de naamconventie zoals `SyncZaaktypen` die ook
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

    private function resolveByNamePrefix(Municipality $municipality, ZaaktypeRole $role): ?Zaaktype
    {
        return Zaaktype::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->where('name', 'like', $role->namePrefix().'%')
            ->first();
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
