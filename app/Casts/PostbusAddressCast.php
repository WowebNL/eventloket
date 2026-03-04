<?php

namespace App\Casts;

use App\ValueObjects\PostbusAddress;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @implements CastsAttributes<PostbusAddress, PostbusAddress|array> */
class PostbusAddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PostbusAddress
    {
        if ($value === null) {
            return null;
        }

        $data = is_array($value) ? $value : json_decode($value, true);

        if (! is_array($data)) {
            return null;
        }

        return PostbusAddress::fromArray($data);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PostbusAddress) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            return json_encode(PostbusAddress::fromArray($value)->toArray());
        }

        throw new InvalidArgumentException('The given value is not a PostbusAddress instance or array.');
    }
}
