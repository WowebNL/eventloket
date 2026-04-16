<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

use RuntimeException;

/**
 * Compileert een JsonLogic-boom naar een PHP-expressie (als string) die tegen
 * een `FormState $s` evalueert. Ondersteunt precies de 11 operators die het
 * evenementformulier gebruikt (var, ==, !=, !!, and, or, if, missing, +,
 * reduce, >=).
 *
 * De `reduce`-operator wordt herkend op het specifieke "tel elementen"-
 * patroon (`reduce(arr, +:1:accumulator, 0)`) en emit `count(...)` —
 * voldoende voor onze use-case.
 */
class JsonLogicCompiler
{
    public function compile(mixed $expression): string
    {
        if (is_bool($expression)) {
            return $expression ? 'true' : 'false';
        }
        if ($expression === null) {
            return 'null';
        }
        if (is_int($expression) || is_float($expression)) {
            return (string) $expression;
        }
        if (is_string($expression)) {
            return var_export($expression, true);
        }

        if (! is_array($expression)) {
            throw new RuntimeException('Unsupported JsonLogic literal type: '.gettype($expression));
        }

        // Lijst-literal (zonder operator-key) — ondersteunen door te laten
        // compileren als array-literal met elk element recursief verwerkt.
        if ($this->isList($expression)) {
            $parts = array_map(fn ($v): string => $this->compile($v), $expression);

            return '['.implode(', ', $parts).']';
        }

        // Operator-object: één key met args-list.
        if (count($expression) !== 1) {
            throw new RuntimeException('JsonLogic operator-object must have exactly one key, got '.count($expression));
        }

        $operator = (string) array_key_first($expression);
        /** @var mixed $args */
        $args = $expression[$operator];

        return $this->compileOperator($operator, $args);
    }

    private function compileOperator(string $operator, mixed $args): string
    {
        return match ($operator) {
            'var' => $this->compileVar($args),
            '==' => $this->compileBinary($args, '==='),
            '!=' => $this->compileBinary($args, '!=='),
            '>=' => $this->compileBinary($args, '>='),
            '<=' => $this->compileBinary($args, '<='),
            '+' => $this->compilePlus($args),
            'and' => $this->compileVariadic($args, '&&', 'true'),
            'or' => $this->compileVariadic($args, '||', 'false'),
            '!!' => $this->compileBang($args),
            'if' => $this->compileIf($args),
            'missing' => $this->compileMissing($args),
            'reduce' => $this->compileReduce($args),
            'cat' => $this->compileCat($args),
            'map' => $this->compileMap($args),
            'merge' => $this->compileMerge($args),
            default => throw new RuntimeException("Unsupported JsonLogic operator: {$operator}"),
        };
    }

    private function compileVar(mixed $args): string
    {
        // `var` kan een string zijn (`"x"`) of een array (`["x"]` of
        // `["x", default]`) of een JsonLogic-expressie die runtime een
        // pad-string oplevert (bv. {"cat": ["gemeenten.", {"var": ...}]}).
        if (is_string($args)) {
            return '$s->get('.var_export($args, true).')';
        }
        if (is_array($args) && isset($args[0]) && is_string($args[0]) && ! $this->isOperatorObject($args)) {
            return '$s->get('.var_export($args[0], true).')';
        }
        if (is_array($args) && $this->isOperatorObject($args)) {
            return '$s->get((string) '.$this->compile($args).')';
        }

        throw new RuntimeException('Unsupported `var` argument shape');
    }

    private function isOperatorObject(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }
        if ($this->isList($value)) {
            return false;
        }

        return count($value) === 1;
    }

    private function compileBinary(mixed $args, string $phpOp): string
    {
        if (! is_array($args) || count($args) !== 2) {
            throw new RuntimeException("Operator `{$phpOp}` expects 2 arguments");
        }
        [$a, $b] = array_values($args);

        return '('.$this->compile($a).' '.$phpOp.' '.$this->compile($b).')';
    }

    private function compileVariadic(mixed $args, string $phpOp, string $neutral): string
    {
        if (! is_array($args) || $args === []) {
            return $neutral;
        }
        $parts = array_map(fn ($v): string => $this->compile($v), array_values($args));

        return '('.implode(' '.$phpOp.' ', $parts).')';
    }

    private function compileBang(mixed $args): string
    {
        $inner = is_array($args) && array_key_exists(0, $args)
            ? $args[0]
            : $args;

        return '((bool) '.$this->compile($inner).')';
    }

    private function compileIf(mixed $args): string
    {
        if (! is_array($args) || count($args) < 2) {
            throw new RuntimeException('`if` expects at least 2 arguments');
        }
        $parts = array_values($args);
        $cond = $this->compile($parts[0]);
        $then = $this->compile($parts[1]);
        $else = isset($parts[2]) ? $this->compile($parts[2]) : 'null';

        return '('.$cond.' ? '.$then.' : '.$else.')';
    }

    private function compileMissing(mixed $args): string
    {
        $keys = is_array($args) ? $args : [$args];
        $encoded = var_export($keys, true);

        // Haalt state-waarden op voor de keys, en retourneert de keys waarvan
        // de waarde null/leeg is. Equivalent aan JsonLogic's `missing`.
        return '(array_values(array_filter('.$encoded.', static fn ($k) => '
            .'$s->get($k) === null || $s->get($k) === \'\'))) ';
    }

    /**
     * Detecteert het "tel-patroon" `reduce(arr, {+: [1, {var:accumulator}]}, 0)`
     * en emit `count(arr ?? [])`. Andere reduce-shapes zijn niet in gebruik.
     */
    private function compileReduce(mixed $args): string
    {
        if (! is_array($args) || count($args) !== 3) {
            throw new RuntimeException('`reduce` expects exactly 3 arguments');
        }
        [$arr, $fn, $init] = array_values($args);

        if ($this->isCountReducePattern($fn, $init)) {
            $arrExpr = $this->compile($arr);

            return '(is_array('.$arrExpr.') ? count('.$arrExpr.') : 0)';
        }

        throw new RuntimeException('Only count-style reduce (+1 accumulator) is supported');
    }

    private function isCountReducePattern(mixed $fn, mixed $init): bool
    {
        if ($init !== 0) {
            return false;
        }
        if (! is_array($fn) || ! isset($fn['+'])) {
            return false;
        }
        $plusArgs = $fn['+'];
        if (! is_array($plusArgs) || count($plusArgs) !== 2) {
            return false;
        }
        // Accepteer [1, {var:accumulator}] én [{var:accumulator}, 1].
        $hasOne = in_array(1, $plusArgs, true);
        $hasAccumulator = false;
        foreach ($plusArgs as $arg) {
            if (is_array($arg) && isset($arg['var']) && $arg['var'] === 'accumulator') {
                $hasAccumulator = true;
            }
        }

        return $hasOne && $hasAccumulator;
    }

    /**
     * Som met variabel aantal operanden — gebruikt o.a. in de risicoscan
     * waar 14 scores worden opgeteld.
     */
    private function compilePlus(mixed $args): string
    {
        if (! is_array($args) || $args === []) {
            return '0';
        }
        $parts = array_map(fn ($v): string => '((float) '.$this->compile($v).')', array_values($args));

        return '('.implode(' + ', $parts).')';
    }

    private function compileCat(mixed $args): string
    {
        if (! is_array($args) || $args === []) {
            return "''";
        }
        $parts = array_map(fn ($v): string => '((string) '.$this->compile($v).')', array_values($args));

        return '('.implode('.', $parts).')';
    }

    private function compileMap(mixed $args): string
    {
        if (! is_array($args) || count($args) !== 2) {
            throw new RuntimeException('`map` expects exactly 2 arguments');
        }
        [$arr, $innerExpr] = array_values($args);
        $arrExpr = $this->compile($arr);

        // Het inner-expression wordt binnen een closure geëvalueerd waarbij
        // elk item tijdelijk wordt overschreven in een sub-FormState: we
        // benaderen het item als variables in een tmp FormState zodat
        // `{var:name}` binnen map correct werkt.
        $innerPhp = $this->compileMapInner($innerExpr);

        return '((function () use ($s) { '
            .'$__items = '.$arrExpr.'; '
            .'if (!is_array($__items)) { return []; } '
            .'$__result = []; '
            .'foreach ($__items as $__item) { '
            .'$__result[] = (function ($s) { return '.$innerPhp.'; })('
            .'\App\EventForm\Transpiler\MapContext::from($s, $__item)); '
            .'} '
            .'return $__result; '
            .'})())';
    }

    private function compileMapInner(mixed $expr): string
    {
        return $this->compile($expr);
    }

    private function compileMerge(mixed $args): string
    {
        if (! is_array($args)) {
            return '[]';
        }
        $parts = array_map(fn ($v): string => '('.$this->compile($v).' ?? [])', array_values($args));

        return 'array_merge('.implode(', ', $parts).')';
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
