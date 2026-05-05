<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class FormMeta implements Arrayable
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $internalName,
        public readonly bool $active,
        public readonly ?string $ofRelease,
        public readonly ?string $ofGitSha,
        public readonly string $generatedAt,
        public readonly string $source,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'internal_name' => $this->internalName,
            'active' => $this->active,
            'of_release' => $this->ofRelease,
            'of_git_sha' => $this->ofGitSha,
            'generated_at' => $this->generatedAt,
            'source' => $this->source,
        ];
    }
}
