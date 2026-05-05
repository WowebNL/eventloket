<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A resolved condition on a field. Represents either a simple conditional
 * or a selectboxes-member check (e.g. X.<key> = true).
 */
class Condition implements Arrayable
{
    public function __construct(
        public readonly bool $show,
        public readonly string $when,
        public readonly string $equals,
        public readonly string $targetType,
        public readonly bool $isSelectboxesMember,
    ) {}

    public function describe(): string
    {
        $verb = $this->show ? 'toon als' : 'verberg als';
        if ($this->isSelectboxesMember) {
            return sprintf('%s [%s].[%s] = true', $verb, $this->when, $this->equals);
        }

        $value = $this->equals === '' ? '' : '['.$this->equals.']';

        return trim(sprintf('%s [%s] = %s', $verb, $this->when, $value));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'show' => $this->show,
            'when' => $this->when,
            'equals' => $this->equals,
            'target_type' => $this->targetType,
            'is_selectboxes_member' => $this->isSelectboxesMember,
            'description' => $this->describe(),
        ];
    }
}
