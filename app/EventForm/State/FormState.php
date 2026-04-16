<?php

declare(strict_types=1);

namespace App\EventForm\State;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Unified state voor het evenementformulier.
 *
 * Bevat:
 *  - velden: de antwoorden van de gebruiker, keyed op veld-key (bv. "soortEvenement"
 *    of "watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken")
 *  - variables: runtime-state die niet door een veld wordt beheerd maar door rules
 *    of service-calls (bv. "evenementInGemeente", "gemeenteVariabelen")
 *  - system: externe context (submission_id, auth_bsn, auth_kvk, etc.)
 *
 * Daarnaast houdt FormState per-veld hidden-overrides bij (gezet door
 * `property`-rules) en per-stap applicable-flags (gezet door
 * `step-(not-)applicable`-rules).
 *
 * Lookups gebruiken dot-notation: `$s->get('gemeenteVariabelen.aanwezigen')`
 * werkt voor geneste arrays / objects. Onbekende paden geven `null` terug.
 */
class FormState implements Arrayable
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $system
     * @param  array<string, bool>  $fieldHiddenOverrides  veld-key → forced hidden state
     * @param  array<string, bool>  $stepApplicable  step-key → applicable flag (default true)
     */
    public function __construct(
        private array $fields = [],
        private array $variables = [],
        private array $system = [],
        private array $fieldHiddenOverrides = [],
        private array $stepApplicable = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Haal een waarde op via dot-notation. Zoekvolgorde:
     *   1) fields['path']                (exacte match, niet gesplitst)
     *   2) fields[head] → ...             (dot-descend)
     *   3) variables[head] → ...
     *   4) system[head] → ...
     */
    public function get(string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        if (array_key_exists($path, $this->fields)) {
            return $this->fields[$path];
        }

        [$head, $rest] = $this->splitPath($path);

        foreach ([$this->fields, $this->variables, $this->system] as $bag) {
            if (array_key_exists($head, $bag)) {
                return $this->descend($bag[$head], $rest);
            }
        }

        return null;
    }

    public function setField(string $key, mixed $value): void
    {
        $this->fields[$key] = $value;
    }

    public function setVariable(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    public function setSystem(string $key, mixed $value): void
    {
        $this->system[$key] = $value;
    }

    public function setFieldHidden(string $fieldKey, bool $hidden): void
    {
        $this->fieldHiddenOverrides[$fieldKey] = $hidden;
    }

    /**
     * Leeg alle rule-driven visibility-overrides. Wordt door de
     * RulesEngine aangeroepen vóór elke pass — anders zou een rule die
     * ooit eens `hidden=false` heeft gezet die waarde voor altijd
     * behouden, ook nadat z'n trigger niet meer waar is. Defaults
     * (component.hidden uit OF + conditional.show/when/eq) nemen het
     * opnieuw over.
     */
    public function resetFieldHiddenOverrides(): void
    {
        $this->fieldHiddenOverrides = [];
    }

    public function isFieldHidden(string $fieldKey): ?bool
    {
        return $this->fieldHiddenOverrides[$fieldKey] ?? null;
    }

    public function setStepApplicable(string $stepKey, bool $applicable): void
    {
        $this->stepApplicable[$stepKey] = $applicable;
    }

    public function isStepApplicable(string $stepKey): bool
    {
        return $this->stepApplicable[$stepKey] ?? true;
    }

    /** @return array<string, bool> */
    public function stepApplicableMap(): array
    {
        return $this->stepApplicable;
    }

    /** @return array<string, bool> */
    public function fieldHiddenMap(): array
    {
        return $this->fieldHiddenOverrides;
    }

    /** @return array<string, mixed> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return $this->variables;
    }

    /**
     * Merge Filament form-data terug in field-state.
     *
     * @param  array<string, mixed>  $data
     */
    public function absorbFields(array $data): void
    {
        $this->fields = array_replace($this->fields, $data);
    }

    /**
     * Set meerdere variabelen in één keer (bv. initial values of service response).
     *
     * @param  array<string, mixed>  $values
     */
    public function absorbVariables(array $values): void
    {
        $this->variables = array_replace($this->variables, $values);
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'fields' => $this->fields,
            'variables' => $this->variables,
            'system' => $this->system,
            'field_hidden' => $this->fieldHiddenOverrides,
            'step_applicable' => $this->stepApplicable,
        ];
    }

    /** @param  array<string, mixed>  $snapshot */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            fields: self::arrayFrom($snapshot, 'fields'),
            variables: self::arrayFrom($snapshot, 'variables'),
            system: self::arrayFrom($snapshot, 'system'),
            fieldHiddenOverrides: self::boolMapFrom($snapshot, 'field_hidden'),
            stepApplicable: self::boolMapFrom($snapshot, 'step_applicable'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->toSnapshot();
    }

    /** @return array{0: string, 1: string} */
    private function splitPath(string $path): array
    {
        $dot = strpos($path, '.');
        if ($dot === false) {
            return [$path, ''];
        }

        return [substr($path, 0, $dot), substr($path, $dot + 1)];
    }

    private function descend(mixed $value, string $rest): mixed
    {
        if ($rest === '') {
            return $value;
        }

        [$head, $next] = $this->splitPath($rest);

        if (is_array($value) && array_key_exists($head, $value)) {
            return $this->descend($value[$head], $next);
        }

        // Filament's CheckboxList bewaart selectboxes als `['buiten', 'route']`
        // (indexed list van strings). OF's rule-triggers gebruiken object-
        // access (`X.buiten` → true/false). Normaliseer: als we bij een
        // indexed-list-of-strings uitkomen, behandel de head als member-check.
        if (is_array($value) && $this->isListOfStrings($value)) {
            // Alleen zinvol als dit de laatste stap in het pad is — sub-paden
            // op een bool teruggeven maakt geen sense.
            $isMember = in_array($head, $value, true);
            if ($next === '') {
                return $isMember;
            }

            return null;
        }

        if (is_object($value) && isset($value->{$head})) {
            return $this->descend($value->{$head}, $next);
        }

        return null;
    }

    /** @param array<int|string, mixed> $value */
    private function isListOfStrings(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            return false;
        }
        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function arrayFrom(array $snapshot, string $key): array
    {
        $value = $snapshot[$key] ?? [];

        /** @var array<string, mixed> $result */
        $result = is_array($value) ? $value : [];

        return $result;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, bool>
     */
    private static function boolMapFrom(array $snapshot, string $key): array
    {
        $value = $snapshot[$key] ?? [];
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $k => $v) {
            if (is_string($k)) {
                $result[$k] = (bool) $v;
            }
        }

        return $result;
    }
}
