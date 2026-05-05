<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Parsers;

use App\Services\OpenForms\Veldenkaart\Data\FormVariable;

class FormVariableParser
{
    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<FormVariable>
     */
    public function parse(array $raw): array
    {
        $result = [];
        foreach ($raw as $entry) {
            $result[] = new FormVariable(
                key: $this->str($entry, 'key'),
                name: $this->str($entry, 'name'),
                source: $this->str($entry, 'source'),
                dataType: $this->str($entry, 'data_type'),
                dataFormat: $this->str($entry, 'data_format'),
                initialValue: $entry['initial_value'] ?? null,
                prefillPlugin: $this->str($entry, 'prefill_plugin'),
                prefillAttribute: $this->str($entry, 'prefill_attribute'),
                prefillIdentifierRole: $this->str($entry, 'prefill_identifier_role'),
                isSensitiveData: (bool) ($entry['is_sensitive_data'] ?? false),
            );
        }

        return $result;
    }

    /** @param array<string, mixed> $source */
    private function str(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
