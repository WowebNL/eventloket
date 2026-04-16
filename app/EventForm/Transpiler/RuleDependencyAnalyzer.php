<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

/**
 * Extract de top-level state-keys die een JsonLogic-expressie **leest**.
 * Gebruikt door de transpiler om per rule te bepalen bij welke stap(pen)
 * hij relevant is (trigger-scope).
 *
 * We retourneren alleen de root-key van elk `var` (dus `gemeenteVariabelen`
 * i.p.v. `gemeenteVariabelen.aanwezigen`) omdat de scope een page-level
 * bucket is, niet een sub-pad.
 *
 * Sommige namen zijn reduce/map-interne variabelen en geen state-reads —
 * die filteren we expliciet uit.
 */
class RuleDependencyAnalyzer
{
    /** Namen die binnen JsonLogic-constructs worden geïntroduceerd, geen state-reads. */
    private const INTERNAL_VARS = [
        'accumulator', // reduce-accumulator
        'current',     // reduce-current (alias-patroon)
        'name',        // map-item field
        'brk_identification',
        'items',
        'all',
        'line',
        'start',
        'end',
        'start_end_equal',
        'passing',
    ];

    /**
     * @return list<string>  Lijst van unieke top-level read-keys.
     */
    public function readKeys(mixed $expression): array
    {
        return array_values(array_unique(
            $this->walk($expression, withinMapItemScope: false)
        ));
    }

    /**
     * @return list<string>
     */
    private function walk(mixed $node, bool $withinMapItemScope): array
    {
        if (! is_array($node) || $node === []) {
            return [];
        }

        if ($this->isList($node)) {
            $out = [];
            foreach ($node as $child) {
                foreach ($this->walk($child, $withinMapItemScope) as $k) {
                    $out[] = $k;
                }
            }

            return $out;
        }

        if (count($node) !== 1) {
            return [];
        }

        $op = (string) array_key_first($node);
        /** @var mixed $args */
        $args = $node[$op];

        if ($op === 'var') {
            return $this->handleVar($args, $withinMapItemScope);
        }

        if ($op === 'map') {
            return $this->handleMap($args);
        }

        if (! is_array($args)) {
            return [];
        }

        $out = [];
        foreach ($args as $child) {
            foreach ($this->walk($child, $withinMapItemScope) as $k) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function handleVar(mixed $args, bool $withinMapItemScope): array
    {
        // Dynamic var (pad opgebouwd via sub-expressie, bv. `{cat: [..., {var: X}]}`):
        // recurse in de sub-expressie zodat die referenties als read worden
        // geteld.
        if (is_array($args) && ! $this->isList($args)) {
            return $this->walk($args, $withinMapItemScope);
        }

        $path = null;
        if (is_string($args)) {
            $path = $args;
        } elseif (is_array($args) && isset($args[0]) && is_string($args[0])) {
            $path = $args[0];
        }

        if ($path === null || $path === '') {
            return [];
        }

        $root = explode('.', $path, 2)[0];

        if (in_array($root, self::INTERNAL_VARS, true)) {
            return [];
        }

        if ($withinMapItemScope) {
            return [];
        }

        return [$root];
    }

    /**
     * @return list<string>
     */
    private function handleMap(mixed $args): array
    {
        if (! is_array($args) || count($args) < 1) {
            return [];
        }
        $parts = array_values($args);
        $out = $this->walk($parts[0] ?? null, withinMapItemScope: false);
        if (isset($parts[1])) {
            foreach ($this->walk($parts[1], withinMapItemScope: true) as $k) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /** @param  array<int|string, mixed>  $arr */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
