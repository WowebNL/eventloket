<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

/**
 * Raw, untyped input from a loader. The parsers consume this and produce
 * the typed VeldenkaartData. Shape mirrors Open Forms API responses.
 */
class RawFormData
{
    /**
     * @param  array<string, mixed>  $form  as returned by GET /api/v2/forms/{uuid}
     * @param  list<array<string, mixed>>  $formSteps  as returned by iterating form.steps
     * @param  list<array<string, mixed>>  $logicRules  as returned by GET /api/v2/forms/{uuid}/logic-rules
     * @param  list<array<string, mixed>>  $variables  as returned by GET /api/v2/forms/{uuid}/variables
     * @param  array<string, mixed>  $meta  additional metadata (e.g. of_release, source)
     */
    public function __construct(
        public readonly array $form,
        public readonly array $formSteps,
        public readonly array $logicRules,
        public readonly array $variables,
        public readonly array $meta = [],
    ) {}
}
