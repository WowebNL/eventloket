<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class MessageDocument implements Arrayable, JsonSerializable
{
    public string $url;

    public int $versie;

    public function __construct(array $data)
    {
        $this->url = $data['url'];
        $this->versie = $data['versie'];
    }

    public static function make(string $url, int $versie): self
    {
        return new self(['url' => $url, 'versie' => $versie]);
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'versie' => $this->versie,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
