<?php

namespace App\ValueObjects\ModelAttributes;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class ZaakReferenceData implements Arrayable, Castable
{
    public readonly Carbon $start_evenement_datetime;

    public readonly Carbon $eind_evenement_datetime;

    public readonly Carbon $registratiedatum_datetime;

    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $risico_classificatie,
        public readonly string $start_evenement,
        public readonly string $eind_evenement,
        public readonly string $registratiedatum,
        public readonly string $status_name,
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
