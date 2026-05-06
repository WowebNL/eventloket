<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Loaders;

use App\Services\OpenForms\Veldenkaart\Data\RawFormData;

interface LoaderInterface
{
    /**
     * @param  string  $formIdentifier  UUID or slug of the form
     */
    public function load(string $formIdentifier): RawFormData;

    public function sourceLabel(): string;
}
