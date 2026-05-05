<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Renderers;

use App\Services\OpenForms\Veldenkaart\Data\VeldenkaartData;

class JsonRenderer
{
    public function render(VeldenkaartData $data): string
    {
        $json = json_encode(
            $data->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode veldenkaart as JSON');
        }

        return $json."\n";
    }
}
