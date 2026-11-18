<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\EventForm\Services\LocationServerCheckInput;
use App\EventForm\Services\LocationServerCheckService;

/**
 * Records the input the municipality check is handed, and otherwise behaves
 * exactly like the real service.
 *
 * Used to prove what reaches the `intersects` loop, which is not visible from
 * the response the check returns.
 */
class RecordingLocationServerCheckService extends LocationServerCheckService
{
    /** @var list<LocationServerCheckInput> */
    public array $inputs = [];

    /**
     * @return array<string, mixed>
     */
    public function execute(LocationServerCheckInput $input): array
    {
        $this->inputs[] = $input;

        return parent::execute($input);
    }

    /**
     * Positions of the first line of the most recent call.
     *
     * @return list<mixed>
     */
    public function lastLinePositions(): array
    {
        $input = $this->inputs[count($this->inputs) - 1] ?? null;

        return $input?->lines[0]['coordinates'] ?? [];
    }
}
