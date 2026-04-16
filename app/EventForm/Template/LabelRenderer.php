<?php

declare(strict_types=1);

namespace App\EventForm\Template;

use App\EventForm\State\FormState;

/**
 * Rendert labels met `{{ var }}`-placeholders uit de FormState.
 *
 * Ondersteunt:
 *  - Simple:   `{{ watIsDeNaamVanHetEvenementVergunning }}`
 *  - Nested:   `{{ gemeenteVariabelen.aanwezigen }}`
 *  - Filter:   `{{ routeDoorGemeentenNamen|join:", " }}`  (alleen `join` voor nu)
 *
 * Onbekende vars → lege string (OF-gedrag). Arrays zonder join-filter →
 * JSON-encoded fallback zodat er geen PHP-warnings ontstaan.
 *
 * Volledige Blade-rendering (voor `content`-componenten met HTML) gaat via
 * Laravel's Blade compiler in een aparte component — niet deze class.
 */
class LabelRenderer
{
    public function render(string $template, FormState $state): string
    {
        if ($template === '' || ! str_contains($template, '{{')) {
            return $template;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/',
            fn (array $m): string => $this->resolve($m[1], $state),
            $template,
        );
    }

    private function resolve(string $expression, FormState $state): string
    {
        [$path, $filter, $argument] = $this->parseExpression($expression);
        $value = $state->get($path);

        return $this->applyFilter($value, $filter, $argument);
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function parseExpression(string $expression): array
    {
        if (! str_contains($expression, '|')) {
            return [trim($expression), null, null];
        }

        [$path, $filterPart] = array_map('trim', explode('|', $expression, 2));

        $filter = $filterPart;
        $argument = null;
        if (str_contains($filterPart, ':')) {
            [$filter, $argument] = array_map('trim', explode(':', $filterPart, 2));
            $argument = $this->stripQuotes($argument);
        }

        return [$path, $filter, $argument];
    }

    private function applyFilter(mixed $value, ?string $filter, ?string $argument): string
    {
        if ($filter === 'join') {
            $separator = $argument ?? ', ';
            if (! is_array($value)) {
                return $this->stringify($value);
            }

            return implode($separator, array_map(
                fn ($v): string => $this->stringify($v),
                $value,
            ));
        }

        return $this->stringify($value);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    private function stripQuotes(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' || $first === "'") && $first === $last) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
