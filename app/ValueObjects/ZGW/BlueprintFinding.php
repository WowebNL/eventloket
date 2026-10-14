<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Enums\BlueprintFindingType;

/**
 * One problem found while checking a zaaktype version against the blueprint
 * prerequisites (see docs/functionaliteiten/zgw-koppelingbeheer.md, section 4.4).
 */
final readonly class BlueprintFinding
{
    /**
     * @param  string  $slot  Blueprint slot, e.g. 'eind_statustype' or 'eigenschap:intern_zaaknummer'
     * @param  string|null  $expected  The koppeling value that no longer matches, for MappedValueNotFound
     */
    public function __construct(
        public string $slot,
        public BlueprintFindingType $type,
        public ?string $expected = null,
    ) {}

    /** Stable identity used for dedup/throttling of repeated identical findings. */
    public function key(): string
    {
        return $this->slot.'|'.$this->type->value.'|'.($this->expected ?? '');
    }
}
