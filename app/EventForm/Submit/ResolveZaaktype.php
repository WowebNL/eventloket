<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\Zaaktype;
use RuntimeException;

/**
 * Zoekt het juiste `Zaaktype` voor een submit.
 *
 * In OF stonden er 45 aparte `zgw-create-zaak`-backends, één per
 * (gemeente × aard)-combinatie. Elk backend had een
 * `case_type_identification`-string die liep via een naamconventie, bv:
 *
 *   "Evenementenvergunning gemeente Heerlen"
 *   "Melding evenement gemeente Maastricht"
 *   "Vooraankondiging gemeente Sittard-Geleen"
 *
 * Die zaaktypes zijn bij ons gesynct (zie `SyncZaaktypen`) en staan in
 * de `zaaktypen`-tabel met exact die namen. We matchen op naam —
 * zelfde regel die `SyncZaaktypen` gebruikt om ze aan een `Municipality`
 * te koppelen.
 */
final class ResolveZaaktype
{
    public function __construct(private readonly DetermineAanvraagType $determineAanvraagType) {}

    public function forState(FormState $state): Zaaktype
    {
        $municipality = $this->resolveMunicipality($state);
        $aard = $this->determineAanvraagType->forState($state);
        $prefix = $this->prefixForAard($aard);

        $zaaktype = Zaaktype::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->where('name', 'like', $prefix.'%')
            ->first();

        if (! $zaaktype) {
            throw new RuntimeException(sprintf(
                'Geen actief zaaktype gevonden voor gemeente "%s" met aard "%s" (zocht op naam like "%s%%").',
                $municipality->name,
                $aard,
                $prefix,
            ));
        }

        return $zaaktype;
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

    private function prefixForAard(string $aard): string
    {
        return match ($aard) {
            DetermineAanvraagType::VERGUNNING => 'Evenementenvergunning',
            DetermineAanvraagType::MELDING => 'Melding',
            DetermineAanvraagType::VOORAANKONDIGING => 'Vooraankondiging',
            default => throw new RuntimeException(sprintf('Onbekende aard "%s".', $aard)),
        };
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
     * submit is the lesser harm; the organiser is sent back through the location
     * step. A state without a location check result (older drafts) is left
     * alone.
     */
    private function assertMunicipalityMatchesLocation(FormState $state, string $brk): void
    {
        $gemeenten = $state->get('inGemeentenResponse.all.object');
        if (! is_array($gemeenten) || $gemeenten === []) {
            return;
        }

        if (! array_key_exists($brk, $gemeenten)) {
            throw new RuntimeException(sprintf(
                'De gemeente uit de FormState (%s) hoort niet bij de gevonden gemeenten voor de opgegeven locatie (%s).',
                $brk,
                implode(', ', array_keys($gemeenten)),
            ));
        }
    }
}
