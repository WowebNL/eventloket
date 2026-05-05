<?php

declare(strict_types=1);

namespace App\EventForm\Template;

use App\EventForm\State\FormState;

/**
 * Rendert labels en content-blokken met Jinja2-achtige syntax uit OF:
 *
 *  - `{{ var }}` / `{{ var.path }}` — simple variable substitution
 *  - `{{ var|join:", " }}` — één filter (de enige die in labels voorkomt)
 *  - `{% get_value var 'key' %}` — nested lookup (OF's custom tag)
 *  - `{% if expr %} ... {% elif expr %} ... {% else %} ... {% endif %}`
 *    — control-flow, geen nested ifs (niet in onze data); operators: `not`,
 *    `and`, `or`, `==`, `!=`, `True`, `False` (en dot-notatie in de operanden).
 *
 * Rendering-volgorde:
 *  1. Control-flow blokken oplossen (behoud alleen actieve takken)
 *  2. `{% get_value %}` tags vervangen
 *  3. `{{ ... }}` placeholders vervangen
 *
 * Onbekende/missing waardes → lege string (OF-gedrag). Niet-evalueerbare
 * condities (parsing-fout) → behandel als false zodat we niet crashen in de
 * view-render.
 */
class LabelRenderer
{
    /**
     * Cache van rendered templates per (FormState-instance, version).
     * Filament rendert per Livewire-roundtrip honderden labels (17
     * stappen × 5-15 placeholders × herhaalde Filament-internal calls).
     * Zonder cache wordt elke regex-parsing en state-lookup opnieuw
     * gedaan; met cache draait 't proces 5-10× sneller per render.
     *
     * Sleutel: `version()` van de state — incrementeert bij elke
     * mutator. Als version ongewijzigd is, is de output identiek →
     * cache-hit. WeakMap zorgt voor automatic cleanup zodra de state-
     * instance wordt opgeruimd.
     *
     * @var \WeakMap<FormState, array{version: int, entries: array<string, string>}>
     */
    private \WeakMap $perStateCache;

    public function __construct()
    {
        $this->perStateCache = new \WeakMap;
    }

    public function render(string $template, FormState $state): string
    {
        if ($template === '') {
            return $template;
        }

        // Geen placeholders → letterlijk teruggeven, niet cachen.
        if (! str_contains($template, '{{') && ! str_contains($template, '{%')) {
            return $template;
        }

        $version = $state->version();
        $bucket = $this->perStateCache[$state] ?? null;

        // Bucket bestaat én version klopt → directe hit
        if ($bucket !== null && $bucket['version'] === $version && isset($bucket['entries'][$template])) {
            return $bucket['entries'][$template];
        }

        // Stale bucket (state is gemuteerd) → opnieuw beginnen.
        if ($bucket === null || $bucket['version'] !== $version) {
            $bucket = ['version' => $version, 'entries' => []];
        }

        $result = $this->renderUncached($template, $state);

        $bucket['entries'][$template] = $result;
        $this->perStateCache[$state] = $bucket;

        return $result;
    }

    private function renderUncached(string $template, FormState $state): string
    {
        if (str_contains($template, '{%')) {
            $template = $this->resolveControlFlow($template, $state);
            $template = $this->resolveGetValue($template, $state);
        }

        if (! str_contains($template, '{{')) {
            return $template;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/',
            fn (array $m): string => $this->resolveExpression($m[1], $state),
            $template,
        );
    }

    private function resolveExpression(string $expression, FormState $state): string
    {
        [$path, $filter, $argument] = $this->parseFilterExpression($expression);
        $value = $state->get($path);

        return $this->applyFilter($value, $filter, $argument);
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function parseFilterExpression(string $expression): array
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

            return implode($separator, array_map(fn ($v): string => $this->stringify($v), $value));
        }

        return $this->stringify($value);
    }

    private function resolveGetValue(string $template, FormState $state): string
    {
        return (string) preg_replace_callback(
            "/\{%\s*get_value\s+(\w+)\s+['\"]([^'\"]+)['\"]\s*%\}/",
            fn (array $m): string => $this->stringify($state->get($m[1].'.'.$m[2])),
            $template,
        );
    }

    /**
     * Scan het template op `{% if %}...{% elif %}...{% else %}...{% endif %}`-
     * blokken en vervang elk door de actieve tak. Niet-geneste ondersteund
     * (onze data heeft geen nested control-flow).
     */
    private function resolveControlFlow(string $template, FormState $state): string
    {
        $pattern = '/\{%\s*if\s+(.+?)\s*%\}(.*?)\{%\s*endif\s*%\}/s';

        return (string) preg_replace_callback($pattern, function (array $m) use ($state): string {
            $ifCond = $m[1];
            $body = $m[2];

            // Splits de body op elif/else-grenzen.
            $branches = $this->splitBranches($body);
            $conditions = [$ifCond];
            foreach ($branches['elifs'] as $elifCond) {
                $conditions[] = $elifCond;
            }

            // Zoek de eerste tak waarvoor de conditie evalueert naar true.
            $texts = $branches['texts'];
            foreach ($conditions as $i => $cond) {
                if ($this->evaluateCondition($cond, $state)) {
                    return $texts[$i];
                }
            }

            return $branches['elseText'];
        }, $template);
    }

    /**
     * @return array{
     *     elifs: list<string>,
     *     texts: list<string>,
     *     elseText: string,
     * }
     */
    private function splitBranches(string $body): array
    {
        // Split eerst op {% else %} voor de else-tak
        $elseParts = preg_split('/\{%\s*else\s*%\}/', $body, 2);
        $beforeElse = $elseParts[0] ?? '';
        $elseText = $elseParts[1] ?? '';

        // Split nu het deel voor `else` op `{% elif EXPR %}`
        $pieces = preg_split('/\{%\s*elif\s+(.+?)\s*%\}/', $beforeElse, -1, PREG_SPLIT_DELIM_CAPTURE);

        // preg_split met PREG_SPLIT_DELIM_CAPTURE levert: [textBefore, elifExpr1, textAfter1, elifExpr2, textAfter2, ...]
        $pieces = is_array($pieces) ? $pieces : [$beforeElse];
        $texts = [$pieces[0] ?? ''];
        $elifs = [];
        $count = count($pieces);
        for ($i = 1; $i < $count; $i += 2) {
            $elifs[] = $pieces[$i];
            $texts[] = $i + 1 < $count ? $pieces[$i + 1] : '';
        }

        return [
            'elifs' => $elifs,
            'texts' => $texts,
            'elseText' => $elseText,
        ];
    }

    /**
     * Simpele expressie-evaluator — geen volledige parser, alleen de patterns
     * die in OF-content voorkomen.
     */
    private function evaluateCondition(string $expression, FormState $state): bool
    {
        $expression = trim($expression);

        // OR splitsen (linkste wint shortcut niet nodig voor correctheid)
        if (preg_match('/^(.+?)\s+or\s+(.+)$/i', $expression, $m)) {
            return $this->evaluateCondition($m[1], $state) || $this->evaluateCondition($m[2], $state);
        }
        // AND splitsen
        if (preg_match('/^(.+?)\s+and\s+(.+)$/i', $expression, $m)) {
            return $this->evaluateCondition($m[1], $state) && $this->evaluateCondition($m[2], $state);
        }

        // Negatie
        if (preg_match('/^not\s+(.+)$/i', $expression, $m)) {
            return ! $this->evaluateCondition($m[1], $state);
        }

        // Vergelijking X == Y of X != Y
        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $expression, $m)) {
            $left = $this->evaluateValue(trim($m[1]), $state);
            $right = $this->evaluateValue(trim($m[3]), $state);

            return $m[2] === '==' ? $left === $right : $left !== $right;
        }

        // Losse var/literal → truthy check
        return (bool) $this->evaluateValue($expression, $state);
    }

    private function evaluateValue(string $token, FormState $state): mixed
    {
        $token = trim($token);
        // Literals
        if ($token === 'True' || $token === 'true') {
            return true;
        }
        if ($token === 'False' || $token === 'false') {
            return false;
        }
        if ($token === 'None' || $token === 'null') {
            return null;
        }
        if (preg_match('/^-?\d+$/', $token)) {
            return (int) $token;
        }
        if (preg_match('/^-?\d*\.\d+$/', $token)) {
            return (float) $token;
        }
        if (preg_match("/^['\"](.*)['\"]$/", $token, $m)) {
            return $m[1];
        }

        // Anders: variabele-pad
        return $state->get($token);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value) && $this->looksLikeIsoDateTime($value)) {
            return $this->humanizeIsoDateTime($value);
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Detecteer een ISO-8601-style datum/tijd-string zoals
     * `2026-04-30T12:00` of `2026-04-30T12:00:00+02:00`. Filament's
     * DateTimePicker slaat z'n waarde in dit format op; in templates
     * willen we dat menselijk getoond zien.
     */
    private function looksLikeIsoDateTime(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/', $value);
    }

    private function humanizeIsoDateTime(string $value): string
    {
        try {
            return \Carbon\Carbon::parse($value, 'Europe/Amsterdam')
                ->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return $value;
        }
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
