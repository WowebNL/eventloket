<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class OpenNotification implements Arrayable
{
    /**
     * @param  array<string, string>  $kenmerken  The channel's kenmerken as sent by the
     *                                            Notificaties API (e.g. `bronorganisatie` on
     *                                            the zaken and documenten channels). Empty
     *                                            when the sender omits them.
     */
    public function __construct(
        public readonly string $actie,
        public readonly string $kanaal,
        public readonly string $resource,
        public readonly string $hoofdObject,
        public readonly string $resourceUrl,
        public readonly string $aanmaakdatum,
        public readonly array $kenmerken = [],
    ) {}

    /**
     * The value of a single kenmerk, or null when the sender did not include it.
     */
    public function kenmerk(string $name): ?string
    {
        $value = $this->kenmerken[$name] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function toArray(): array
    {
        return [
            'actie' => $this->actie,
            'kanaal' => $this->kanaal,
            'resource' => $this->resource,
            'hoofdObject' => $this->hoofdObject,
            'resourceUrl' => $this->resourceUrl,
            'aanmaakdatum' => $this->aanmaakdatum,
            'kenmerken' => $this->kenmerken,
        ];
    }

    /**
     * Rebuild the object from a serialized queue payload.
     *
     * Payloads queued before `kenmerken` existed carry only the six original
     * properties. A readonly promoted property cannot declare a default that
     * unserialize() would apply, so those payloads would leave it uninitialized;
     * filling it in here keeps already-queued (and failed) jobs replayable.
     *
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->actie = (string) ($data['actie'] ?? '');
        $this->kanaal = (string) ($data['kanaal'] ?? '');
        $this->resource = (string) ($data['resource'] ?? '');
        $this->hoofdObject = (string) ($data['hoofdObject'] ?? '');
        $this->resourceUrl = (string) ($data['resourceUrl'] ?? '');
        $this->aanmaakdatum = (string) ($data['aanmaakdatum'] ?? '');
        $this->kenmerken = self::normaliseKenmerken($data['kenmerken'] ?? null);
    }

    /**
     * Reduce a kenmerken payload to the string values we can match on; anything
     * else (nested arrays, nulls) is dropped rather than carried along.
     *
     * @return array<string, string>
     */
    public static function normaliseKenmerken(mixed $kenmerken): array
    {
        if (! is_array($kenmerken)) {
            return [];
        }

        $normalised = [];

        foreach ($kenmerken as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) {
                $normalised[$name] = (string) $value;
            }
        }

        return $normalised;
    }
}
