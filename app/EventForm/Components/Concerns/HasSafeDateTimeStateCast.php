<?php

declare(strict_types=1);

namespace App\EventForm\Components\Concerns;

use App\EventForm\Components\StateCasts\SafeDateTimeStateCast;
use Filament\Schemas\Components\StateCasts\DateTimeStateCast;

/**
 * Replaces the default {@see DateTimeStateCast} with the null-safe
 * {@see SafeDateTimeStateCast} on the component's state casts.
 *
 * The default cast is appended by Filament before any `->stateCast()` cast and
 * runs first on the read path, so it can only be swapped by overriding
 * `getDefaultStateCasts()` on a component subclass, which is what this trait
 * does.
 */
trait HasSafeDateTimeStateCast
{
    /**
     * @return array<int, mixed>
     */
    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn (mixed $cast): mixed => $cast instanceof DateTimeStateCast
                ? app(SafeDateTimeStateCast::class, [
                    'format' => $this->getFormat(),
                    'internalFormat' => $this->getInternalFormat(),
                    'timezone' => $this->getTimezone(),
                ])
                : $cast,
            parent::getDefaultStateCasts(),
        );
    }
}
