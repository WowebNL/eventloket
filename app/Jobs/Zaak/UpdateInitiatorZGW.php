<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Zaak;
use App\ValueObjects\OzZaak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Woweb\Openzaak\Openzaak;

/**
 * Zet de initiator-rol op de ZGW-zaak op basis van het initiator-blok
 * in de FormState-snapshot. Twee varianten — matcht OF:
 *
 * - Heeft de aanvrager een KvK-nummer? → `niet_natuurlijk_persoon`
 *   (statutaireNaam, kvkNummer, contactpersoon)
 * - Anders → `natuurlijk_persoon` (voornamen, geslachtsnaam, adres)
 *
 * In de oude flow bestond er al een initiator-rol (door OF aangemaakt)
 * en deed deze job een PUT. In de nieuwe flow maken wij de zaak zelf
 * aan zonder initiator, dus moet hier een POST (nieuw rol) gebeuren.
 * Het initiator-roltype wordt opgezocht in de catalogi.
 */
class UpdateInitiatorZGW implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(Openzaak $openzaak, ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $initiator = $map->buildInitiator($state);
        if (empty($initiator)) {
            return;
        }

        $ozZaak = new OzZaak(...$openzaak->get($this->zaak->zgw_zaak_url.'?expand=rollen')->toArray());
        $roltype = $this->findInitiatorRoltype($openzaak, $ozZaak->zaaktype);
        if (! $roltype) {
            return;
        }

        $rolData = isset($initiator['kvk']) && $initiator['kvk']
            ? $this->buildNietNatuurlijkPersoonRol($ozZaak->url, $roltype, $initiator)
            : $this->buildNatuurlijkPersoonRol($ozZaak->url, $roltype, $state, $initiator);

        $openzaak->zaken()->rollen()->store($rolData);
    }

    private function findInitiatorRoltype(Openzaak $openzaak, string $zaaktypeUrl): ?string
    {
        $roltypen = $openzaak->catalogi()->roltypen()->getAll(['zaaktype' => $zaaktypeUrl]);
        $initiator = collect($roltypen)->first(fn ($r) => ($r['omschrijvingGeneriek'] ?? null) === 'initiator');

        return $initiator['url'] ?? null;
    }

    /** @param  array<string, mixed>  $initiator */
    private function buildNietNatuurlijkPersoonRol(string $zaakUrl, string $roltype, array $initiator): array
    {
        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'niet_natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
            'betrokkeneIdentificatie' => array_filter([
                'statutaireNaam' => $initiator['organisatie_naam'] ?? null,
                'annIdentificatie' => $initiator['kvk'],
                'kvkNummer' => $initiator['kvk'],
            ]),
        ];
    }

    /** @param  array<string, mixed>  $initiator */
    private function buildNatuurlijkPersoonRol(string $zaakUrl, string $roltype, FormState $state, array $initiator): array
    {
        $voornaam = (string) $state->get('watIsUwVoornaam');
        $achternaam = (string) $state->get('watIsUwAchternaam');
        $adres = $state->get('natuurlijkPersoonAdres');

        $rolData = [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
            'betrokkeneIdentificatie' => array_filter([
                'geslachtsnaam' => $achternaam !== '' ? $achternaam : null,
                'voornamen' => $voornaam !== '' ? $voornaam : null,
            ]),
        ];

        if (is_array($adres) && Arr::has($adres, ['postcode', 'plaatsnaam', 'huisnummer'])
            && (empty($adres['land']) || strtolower((string) $adres['land']) === 'nederland')) {
            $rolData['betrokkeneIdentificatie']['verblijfsadres'] = [
                'aoaIdentificatie' => config('app.name').'-persoonsadres-'.Str::uuid(),
                'wplWoonplaatsNaam' => $adres['plaatsnaam'] ?? null,
                'gorOpenbareRuimteNaam' => 'adres',
                'aoaPostcode' => $adres['postcode'] ?? null,
                'aoaHuisnummer' => $adres['huisnummer'] ?? null,
                'aoaHuisletter' => $adres['huisletter'] ?? null,
                'aoaHuisnummertoevoeging' => $adres['huisnummertoevoeging'] ?? null,
            ];
        }

        return $rolData;
    }
}
