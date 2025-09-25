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

    public readonly Carbon $startdatum_datetime;

    public readonly Carbon $registratiedatum_datetime;

    public readonly ?array $otherParams;

    public readonly ?string $data_object_url;

    /*  @var CatalogiEigenschap[]|[] $eigenschappen */
    public readonly array $eigenschappen;

    public readonly array $eigenschappen_key_value;

    public readonly ?Rol $initiator;

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

        $this->status_name = Arr::has($this->_expand, 'status._expand.statustype.omschrijving')
            ? $this->_expand['status']['_expand']['statustype']['omschrijving']
            : null;

        $this->data_object_url = Arr::has($this->_expand, 'zaakobjecten.0.object') && str_contains(Arr::get($this->_expand, 'zaakobjecten.0.object'), config('openzaak.objectsapi.url'))
            ? Arr::get($this->_expand, 'zaakobjecten.0.object')
            : null;

        $this->eigenschappen = Arr::has($this->_expand, 'eigenschappen')
            ? Arr::map($this->_expand['eigenschappen'], fn ($item) => new ZaakEigenschap(...$item))
            : [];

        $this->eigenschappen_key_value = $this->eigenschappen
            ? Arr::mapWithKeys($this->eigenschappen, fn ($item) => [$item->naam => $item->waarde])
            : [];

        $this->otherParams = $otherParams;
        $this->startdatum_datetime = Carbon::parse($this->startdatum);
        $this->registratiedatum_datetime = Carbon::parse($this->registratiedatum);
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
        ];
    }
}
