<?php

namespace App\ValueObjects\ModelAttributes;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

final readonly class ZaakReferenceData implements Arrayable, Castable
{
    public Carbon $start_evenement_datetime;

    public Carbon $eind_evenement_datetime;

    public Carbon $registratiedatum_datetime;

    public ?array $otherParams;

    public function __construct(
        public string $risico_classificatie,
        public string $start_evenement,
        public string $eind_evenement,
        public string $registratiedatum,
        public string $status_name,
        public ?string $naam_evenement = null,
        ...$otherParams
    ) {
        $this->start_evenement_datetime = Carbon::parse($this->start_evenement);
        $this->eind_evenement_datetime = Carbon::parse($this->eind_evenement);
        $this->registratiedatum_datetime = Carbon::parse($this->registratiedatum);
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'risico_classificatie' => $this->risico_classificatie,
            'start_evenement' => $this->start_evenement,
            'eind_evenement' => $this->eind_evenement,
            'registratiedatum' => $this->registratiedatum,
            'status_name' => $this->status_name,
            'naam_evenement' => $this->naam_evenement,
        ];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(
                Model $model,
                string $key,
                mixed $value,
                array $attributes,
            ): ZaakReferenceData {
                return new ZaakReferenceData(...json_decode($value, true));
            }

            public function set(
                Model $model,
                string $key,
                mixed $value,
                array $attributes,
            ): array {
                /** @var ZaakReferenceData $value */
                return ['reference_data' => json_encode($value->toArray())];
            }
        };
    }
}
