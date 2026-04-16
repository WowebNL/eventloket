<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class Step implements Arrayable
{
    /** @param  list<Field>  $fields */
    public function __construct(
        public readonly int $index,
        public readonly string $uuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $internalName,
        public readonly bool $loginRequired,
        public readonly bool $isReusable,
        public readonly array $fields,
    ) {}

    public function fieldCountRecursive(): int
    {
        $count = 0;
        foreach ($this->fields as $field) {
            $count += $this->countField($field);
        }

        return $count;
    }

    private function countField(Field $field): int
    {
        $self = $field->isContent ? 0 : 1;
        foreach ($field->children as $child) {
            $self += $this->countField($child);
        }

        return $self;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'internal_name' => $this->internalName,
            'login_required' => $this->loginRequired,
            'is_reusable' => $this->isReusable,
            'field_count' => $this->fieldCountRecursive(),
            'fields' => array_map(static fn (Field $f): array => $f->toArray(), $this->fields),
        ];
    }
}
