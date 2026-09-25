<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Archiefnominatie;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Archiefstatus;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakEigenschapData;

/**
 * App-facing read model over the package {@see ZaakData} DTO.
 *
 * Replaces the handwritten OzZaak value object. It keeps OzZaak's public shape
 * (so call sites are unchanged) but derives everything from the tolerant DTO:
 * scalar fields come from the DTO, while the app-specific derivations (event
 * addresses, initiator, flattened eigenschappen, status name) are computed here
 * from the expanded relations. Missing fields hydrate to null instead of
 * throwing, which fixes the pilot's fatals on optional fields.
 *
 * @property-read list<ZaakEigenschapData> $eigenschappen
 */
final class ZaakReadModel implements Arrayable
{
    public readonly string $uuid;

    public readonly string $url;

    public readonly string $identificatie;

    public readonly string $zaaktype;

    public readonly string $omschrijving;

    public readonly ?string $startdatum;

    public readonly ?string $registratiedatum;

    public readonly ?string $einddatum;

    public readonly ?string $einddatumGepland;

    public readonly ?string $uiterlijkeEinddatumAfdoening;

    public readonly ?string $bronorganisatie;

    /**
     * The archiving regime the zaaksysteem holds for this zaak. Read by the
     * archive module to decide whether a zaak may be destroyed; every other
     * consumer can ignore these three.
     */
    public readonly ?Archiefnominatie $archiefnominatie;

    public readonly ?Archiefstatus $archiefstatus;

    public readonly ?CarbonImmutable $archiefactiedatum;

    /** @var array<string, mixed>|null */
    public readonly ?array $zaakgeometrie;

    /** @var array<string, mixed>|null */
    public readonly ?array $status;

    public readonly ?string $status_name;

    public readonly ?string $statustype_url;

    public readonly ?string $data_object_url;

    /** @var list<ZaakEigenschapData> */
    public readonly array $eigenschappen;

    /** @var array<string, mixed> */
    public readonly array $eigenschappen_key_value;

    /** @var array<string, mixed>|null */
    public readonly ?array $initiator;

    /** @var list<array<string, mixed>> */
    public readonly array $deelzaken;

    /** @var array<string, mixed>|null */
    public readonly ?array $resultaat;

    /** @var array<string, mixed>|null */
    public readonly ?array $resultaattype;

    /** @var list<string> */
    public array $zaakAddresses;

    public function __construct(ZaakData $dto)
    {
        $raw = $dto->raw;
        /** @var array<string, mixed> $expand */
        $expand = $raw['_expand'] ?? [];

        $this->uuid = (string) ($raw['uuid'] ?? $dto->url?->uuid() ?? '');
        $this->url = (string) $dto->url;
        $this->identificatie = (string) ($raw['identificatie'] ?? '');
        $this->zaaktype = (string) $dto->zaaktype;
        $this->omschrijving = (string) ($raw['omschrijving'] ?? '');
        $this->startdatum = isset($raw['startdatum']) ? (string) $raw['startdatum'] : null;
        $this->registratiedatum = isset($raw['registratiedatum']) ? (string) $raw['registratiedatum'] : null;
        $this->einddatum = isset($raw['einddatum']) ? (string) $raw['einddatum'] : null;
        $this->einddatumGepland = isset($raw['einddatumGepland']) ? (string) $raw['einddatumGepland'] : null;
        $this->uiterlijkeEinddatumAfdoening = isset($raw['uiterlijkeEinddatumAfdoening']) ? (string) $raw['uiterlijkeEinddatumAfdoening'] : null;
        $this->bronorganisatie = isset($raw['bronorganisatie']) ? (string) $raw['bronorganisatie'] : null;
        $this->zaakgeometrie = $dto->zaakgeometrie?->value;

        // Taken from the DTO rather than $raw: the package already casts these
        // to their enums and to a CarbonImmutable.
        $this->archiefnominatie = $dto->archiefnominatie;
        $this->archiefstatus = $dto->archiefstatus;
        $this->archiefactiedatum = $dto->archiefactiedatum;

        $this->status = $expand['status'] ?? null;
        $this->status_name = Arr::get($expand, 'status._expand.statustype.omschrijving');
        $this->statustype_url = Arr::get($expand, 'status._expand.statustype.url');

        $this->data_object_url = Arr::first(
            $expand['zaakobjecten'] ?? [],
            fn ($item) => isset($item['object']) && str_contains((string) $item['object'], (string) config('openzaak.objectsapi.url'))
        )['object'] ?? null;

        $this->eigenschappen = array_map(
            fn (array $eigenschap) => ZaakEigenschapData::from($eigenschap),
            array_values($expand['eigenschappen'] ?? [])
        );
        $this->eigenschappen_key_value = collect($this->eigenschappen)
            ->mapWithKeys(fn (ZaakEigenschapData $eigenschap) => [(string) $eigenschap->naam => $eigenschap->waarde])
            ->all();

        // The initiator rol is kept as its raw associative array: it is only used
        // to build a display label and to copy the rol verbatim onto a deelzaak.
        $initiator = Arr::first(
            $expand['rollen'] ?? [],
            fn ($rol) => ($rol['omschrijvingGeneriek'] ?? null) === 'initiator'
        );
        $this->initiator = is_array($initiator) ? $initiator : null;

        $this->deelzaken = $expand['deelzaken'] ?? [];
        $this->resultaat = $expand['resultaat'] ?? null;
        $this->resultaattype = Arr::get($expand, 'resultaat._expand.resultaattype');

        $this->zaakAddresses = $this->extractEventAddresses($expand['zaakobjecten'] ?? []);
    }

    /**
     * Hydrate directly from a decoded ZGW response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(ZaakData::from($data));
    }

    /**
     * The event addresses linked to the zaak ("Adres van het evenement").
     *
     * @param  list<array<string, mixed>>  $zaakobjecten
     * @return list<string>
     */
    private function extractEventAddresses(array $zaakobjecten): array
    {
        $addresses = [];
        foreach ($zaakobjecten as $zaakobject) {
            if (($zaakobject['objectType'] ?? null) !== 'adres'
                || ($zaakobject['relatieomschrijving'] ?? null) !== 'Adres van het evenement') {
                continue;
            }

            $identificatie = $zaakobject['objectIdentificatie'] ?? [];
            $addresses[] = implode(' ', [
                $identificatie['postcode'] ?? '',
                $identificatie['huisnummer'] ?? '',
                $identificatie['huisletter'] ?? '',
                $identificatie['huisnummertoevoeging'] ?? '',
                $identificatie['wplWoonplaatsNaam'] ?? '',
            ]);
        }

        return $addresses;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'identificatie' => $this->identificatie,
            'omschrijving' => $this->omschrijving,
            'startdatum' => $this->startdatum,
            'status_name' => $this->status_name,
            'eigenschappen' => $this->eigenschappen,
            'einddatum' => $this->einddatum,
            'einddatumGepland' => $this->einddatumGepland,
            'zaakgeometrie' => $this->zaakgeometrie,
            'uiterlijkeEinddatumAfdoening' => $this->uiterlijkeEinddatumAfdoening,
            'resultaat' => $this->resultaat,
            'resultaattype' => $this->resultaattype,
            'archiefnominatie' => $this->archiefnominatie?->value,
            'archiefstatus' => $this->archiefstatus?->value,
            'archiefactiedatum' => $this->archiefactiedatum?->toDateString(),
        ];
    }
}
