<?php

namespace App\ValueObjects;

use App\ValueObjects\ZGW\CatalogiEigenschap;
use App\ValueObjects\ZGW\Rol;
use App\ValueObjects\ZGW\ZaakEigenschap;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class OzZaak implements Arrayable
{
    public readonly ?string $status_name;

    public readonly ?array $status;

    public readonly Carbon $startdatum_datetime;

    public readonly Carbon $registratiedatum_datetime;

    public readonly ?array $otherParams;

    public readonly ?string $data_object_url;

    /*  @var CatalogiEigenschap[]|[] $eigenschappen */
    public readonly array $eigenschappen;

    public readonly array $eigenschappen_key_value;

    public readonly ?Rol $initiator;

    public ?array $zaakAddresses;

    public readonly ?array $resultaat;

    public readonly ?array $resultaattype;

    public function __construct(
        public readonly string $uuid,
        public readonly string $url,
        public readonly string $identificatie,
        public readonly string $zaaktype,
        public readonly string $omschrijving,
        public readonly string $startdatum,
        public readonly string $registratiedatum,
        public readonly ?string $einddatum,
        public readonly ?string $einddatumGepland,
        public readonly ?string $uiterlijkeEinddatumAfdoening,
        public readonly ?string $bronorganisatie,
        public readonly ?array $zaakgeometrie,
        private readonly array $_expand = [],
        ...$otherParams
    ) {
        $this->initiator = Arr::has($this->_expand, 'rollen')
            ? new Rol(...Arr::first(Arr::where($this->_expand['rollen'], fn ($item) => $item['omschrijvingGeneriek'] === 'initiator')))
            : null;

        $this->status = Arr::has($this->_expand, 'status')
            ? $this->_expand['status']
            : null;

        $this->status_name = Arr::has($this->_expand, 'status._expand.statustype.omschrijving')
            ? $this->_expand['status']['_expand']['statustype']['omschrijving']
            : null;

        $this->data_object_url = Arr::first(
            $this->_expand['zaakobjecten'] ?? [],
            fn ($item) => isset($item['object']) && str_contains($item['object'], config('openzaak.objectsapi.url'))
        )['object'] ?? null;

        $this->eigenschappen = Arr::has($this->_expand, 'eigenschappen')
            ? Arr::map($this->_expand['eigenschappen'], fn ($item) => new ZaakEigenschap(...$item))
            : [];

        $this->eigenschappen_key_value = $this->eigenschappen
            ? Arr::mapWithKeys($this->eigenschappen, fn ($item) => [$item->naam => $item->waarde])
            : [];

        $this->resultaat = Arr::has($this->_expand, 'resultaat')
            ? Arr::get($this->_expand, 'resultaat')
            : null;

        $this->resultaattype = Arr::has($this->_expand, 'resultaat._expand.resultaattype')
            ? Arr::get($this->_expand, 'resultaat._expand.resultaattype')
            : null;

        $this->otherParams = $otherParams;
        $this->startdatum_datetime = Carbon::parse($this->startdatum);
        $this->registratiedatum_datetime = Carbon::parse($this->registratiedatum);
        $this->setZaakAddresses();
    }

    public function setZaakAddresses(): void
    {
        $addresses = [];
        if (isset($this->_expand['zaakobjecten']) && $this->_expand['zaakobjecten']) {
            foreach ($this->_expand['zaakobjecten'] as $zaakobject) {
                if ($zaakobject['objectType'] == 'adres' && $zaakobject['relatieomschrijving'] == 'Adres van het evenement') {
                    $addresses[] = implode(' ', [
                        $zaakobject['objectIdentificatie']['postcode'] ?? '',
                        $zaakobject['objectIdentificatie']['huisnummer'] ?? '',
                        $zaakobject['objectIdentificatie']['huisletter'] ?? '',
                        $zaakobject['objectIdentificatie']['huisnummertoevoeging'] ?? '',
                        $zaakobject['objectIdentificatie']['wplWoonplaatsNaam'] ?? '',
                    ]);
                }
            }
        }

        $this->zaakAddresses = $addresses;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'identificatie' => $this->identificatie,
            'omschrijving' => $this->omschrijving,
            'startdatum' => $this->startdatum,
            'status_name' => $this->status_name,
            'eigenschappen' => $this->eigenschappen,
            'einddatum' => $this->einddatum ? Carbon::parse($this->einddatum) : null,
            'einddatumGepland' => $this->einddatumGepland ? Carbon::parse($this->einddatumGepland) : null,
            'zaakgeometrie' => $this->zaakgeometrie,
            'uiterlijkeEinddatumAfdoening' => $this->uiterlijkeEinddatumAfdoening ? Carbon::parse($this->uiterlijkeEinddatumAfdoening) : null,
            'resultaat' => $this->resultaat,
            'resultaattype' => $this->resultaattype,
        ];
    }
}
