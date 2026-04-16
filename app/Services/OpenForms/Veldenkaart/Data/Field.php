<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class Field implements Arrayable
{
    /**
     * @param  list<FieldOption>  $options
     * @param  array<string, mixed>  $validate
     * @param  array<string, mixed>|null  $prefill
     * @param  list<Field>  $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly bool $hidden,
        public readonly string $description,
        public readonly string $tooltip,
        public readonly array $options,
        public readonly ?string $optionsSource,
        public readonly array $validate,
        public readonly mixed $defaultValue,
        public readonly ?array $prefill,
        public readonly ?Condition $conditional,
        public readonly ?string $customConditional,
        public readonly ?int $depth,
        public readonly string $stepSlug,
        public readonly array $children = [],
        public readonly bool $isContent = false,
        public readonly ?string $contentHtml = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'hidden' => $this->hidden,
            'description' => $this->description,
            'tooltip' => $this->tooltip,
            'is_content' => $this->isContent,
            'content_html' => $this->contentHtml,
            'options' => array_map(static fn (FieldOption $o): array => $o->toArray(), $this->options),
            'options_source' => $this->optionsSource,
            'validate' => $this->validate,
            'default_value' => $this->defaultValue,
            'prefill' => $this->prefill,
            'conditional' => $this->conditional?->toArray(),
            'custom_conditional' => $this->customConditional,
            'depth' => $this->depth,
            'step_slug' => $this->stepSlug,
            'children' => array_map(static fn (Field $f): array => $f->toArray(), $this->children),
        ];
    }
}
