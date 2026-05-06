<?php

namespace App\Http\Controllers\Api;

use App\EventForm\Services\LocationServerCheckInput;
use App\EventForm\Services\LocationServerCheckService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationServerCheckRequest;
use Illuminate\Http\JsonResponse;

class LocationServerController extends Controller
{
    public function __construct(
        private readonly LocationServerCheckService $service,
    ) {}

    public function check(LocationServerCheckRequest $request): JsonResponse
    {
        $data = $request->validated();

        $input = new LocationServerCheckInput(
            polygons: $this->decodeAsList($data['polygons'] ?? null),
            line: $this->decodeAsObject($data['line'] ?? null),
            lines: $this->decodeAsList($data['lines'] ?? null),
            addresses: $this->decodeAsList($data['addresses'] ?? null),
            address: $this->decodeAsObject($data['address'] ?? null),
        );

        return response()->json(['data' => $this->service->execute($input)]);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function decodeAsList(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        /** @var list<array<string, mixed>> $result */
        $result = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeAsObject(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
