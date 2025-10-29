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

    public ?string $naam_locatie_evenement;

    public function __construct(
        public string $start_evenement,
        public string $eind_evenement,
        public string $registratiedatum,
        public string $status_name,
        public ?string $risico_classificatie = null,
        public ?string $naam_locatie_eveneme = null, // due to limit char restriction in OZ
        public ?string $naam_evenement = null,
        public ?string $organisator = null,
        public ?string $resultaat = null,
        ...$otherParams
    ) {
        $this->start_evenement_datetime = Carbon::parse($this->start_evenement);
        $this->eind_evenement_datetime = Carbon::parse($this->eind_evenement);
        $this->registratiedatum_datetime = Carbon::parse($this->registratiedatum);
        if ($this->naam_locatie_eveneme) {
            $this->naam_locatie_evenement = $this->naam_locatie_eveneme;
        } else {
            $this->naam_locatie_evenement = $otherParams['naam_locatie_evenement'] ?? null;
        }
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
            'naam_locatie_evenement' => $this->naam_locatie_evenement,
            'organisator' => $this->organisator,
            'resultaat' => $this->resultaat,
        ];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        /**
         * @implements CastsAttributes<TGet, TSet>
         *
         * @template TGet of ZaakReferenceData
         * @template TSet of ZaakReferenceData
         */
        return new class implements CastsAttributes
        {
            /**
             * Transform the attribute from the underlying model values.
             *
             * @param  array<string, mixed>  $attributes
             */
            public function get(
                Model $model,
                string $key,
                mixed $value,
                array $attributes,
            ): ZaakReferenceData {
                return new ZaakReferenceData(...json_decode($value, true));
            }

            /**
             * Transform the attribute to its underlying model values.
             *
             * @param  ZaakReferenceData  $value
             * @param  array<string, mixed>  $attributes
             * @return array<string, mixed>
             */
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
