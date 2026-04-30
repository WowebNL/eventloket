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
 *  - variables: runtime-state die niet door een veld wordt beheerd maar door
 *    afgeleide-state (FormDerivedState) of service-calls
 *    (bv. "evenementInGemeente", "gemeenteVariabelen")
 *  - system: externe context (submission_id, auth_bsn, auth_kvk, etc.)
 *
 * Veld-zichtbaarheid en stap-applicability worden volledig pure-functioneel
 * berekend door FormFieldVisibility en FormStepApplicability. FormState
 * delegeert daar naartoe; er is geen rule-driven override-bag meer.
 *
 * Lookups gebruiken dot-notation: `$s->get('gemeenteVariabelen.aanwezigen')`
 * werkt voor geneste arrays / objects. Onbekende paden geven `null` terug.
 */
class FormState implements Arrayable
{
    /**
     * @param  array<string, mixed>  $values  gedeelde pool voor veld-waarden
     *                                        + form-variables — in OF zijn dit
     *                                        dezelfde bak. Een rule die
     *                                        `setVariable('watIsUwVoornaam', X)`
     *                                        doet vult daarmee ook het Filament-
     *                                        veld met die key.
     * @param  array<string, mixed>  $system  authUser, authOrganisation, submission_id etc.
     */
    public function __construct(
        private array $values = [],
        private array $system = [],
    ) {}

    /**
     * Memoization-cache voor `get()`-resultaten. Per state-instantie
     * wordt een gegeven dot-pad maar één keer gecomputed; mutators
     * (`setField`/`setVariable`/etc.) leegmaken de cache. In een typisch
     * EventFormPage-render wordt `get('inGemeentenResponse.all.items')`
     * door tien hidden-closures + templates aangeroepen — zonder cache
     * is dat tien keer een dot-walk + delegatie naar FormDerivedState,
     * elke keer met een nieuwe instantie.
     *
     * @var array<string, mixed>
     */
    private array $getCache = [];

    /**
     * Helper-instances die FormState als input nemen. We hergebruiken
     * één instance per state-lifetime i.p.v. een nieuwe te maken bij
     * elke `get()`-call.
     */
    private ?FormDerivedState $derivedState = null;

    private ?FormSystemDerivedState $systemDerivedState = null;

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Haal een waarde op via dot-notation. Zoekvolgorde:
     *   1) values['path']  (exacte match op unified fields+variables bucket)
     *   2) values[head] → dot-descend
     *   3) system[head] → dot-descend
     */
    public function get(string $path): mixed
    {
        if ($path === '') {
            return null;
        }
        // Memoize: cache-hit op identiek pad → direct return.
        if (array_key_exists($path, $this->getCache)) {
            return $this->getCache[$path];
        }

        $value = $this->resolve($path);
        $this->getCache[$path] = $value;

        return $value;
    }

    private function resolve(string $path): mixed
    {
        // Afgeleide variabelen (FormDerivedState) krijgen voorrang. We
        // checken op de root-segment-key zodat zowel `'evenementInGemeente'`
        // als `'evenementInGemeente.brk_identification'` (dot-descend) werkt.
        // Levert de berekening `null` op (geen primitieve input om uit
        // af te leiden), dan valt 't door naar de values-bag — handig
        // voor service-fetched waarden zoals `inGemeentenResponse`.
        [$head, $rest] = $this->splitPath($path);
        if (isset(FormDerivedState::COMPUTED_KEYS[$head])) {
            $derived = ($this->derivedState ??= new FormDerivedState($this))->get($head);
            if ($derived !== null) {
                return $rest === '' ? $derived : $this->descend($derived, $rest);
            }
        }

        // System-bag-paden (`system.X`): gemigreerde keys komen uit
        // FormSystemDerivedState. Bij `null` valt 't door naar de
        // gewone system-bag.
        if ($head === 'system' && $rest !== '') {
            [$systemKey, $systemRest] = $this->splitPath($rest);
            if (isset(FormSystemDerivedState::COMPUTED_KEYS[$systemKey])) {
                $derived = ($this->systemDerivedState ??= new FormSystemDerivedState($this))->get($systemKey);
                if ($derived !== null) {
                    return $systemRest === '' ? $derived : $this->descend($derived, $systemRest);
                }
            }
        }

        if (array_key_exists($path, $this->values)) {
            return $this->values[$path];
        }

        foreach ([$this->values, $this->system] as $bag) {
            if (array_key_exists($head, $bag)) {
                return $this->descend($bag[$head], $rest);
            }
        }

        return null;
    }

    public function setField(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
        $this->getCache = [];
    }

    public function setVariable(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
        $this->getCache = [];
    }

    public function setSystem(string $key, mixed $value): void
    {
        $this->system[$key] = $value;
        $this->getCache = [];
    }

    public function isFieldHidden(string $fieldKey): ?bool
    {
        if (! isset(FormFieldVisibility::COMPUTED_KEYS[$fieldKey])) {
            return null; // geen mening → caller valt terug op step-default
        }

        return (new FormFieldVisibility($this))->get($fieldKey);
    }

    private ?FormStepApplicability $stepApplicabilityHelper = null;

    public function isStepApplicable(string $stepKey): bool
    {
        if (! isset(FormStepApplicability::COMPUTED_STEPS[$stepKey])) {
            return true; // geen rules → default applicable
        }

        $derived = ($this->stepApplicabilityHelper ??= new FormStepApplicability($this))->get($stepKey);

        return $derived ?? true; // null uit get() = geen mening = default applicable
    }

    /** @return array<string, mixed> */
    public function fields(): array
    {
        return $this->values;
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return $this->values;
    }

    /**
     * Merge Filament form-data terug in field-state.
     *
     * @param  array<string, mixed>  $data
     */
    public function absorbFields(array $data): void
    {
        $this->values = array_replace($this->values, $data);
        $this->getCache = [];
    }

    /**
     * Set meerdere variabelen in één keer (bv. initial values of service response).
     *
     * @param  array<string, mixed>  $values
     */
    public function absorbVariables(array $values): void
    {
        $this->values = array_replace($this->values, $values);
        $this->getCache = [];
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'values' => $this->values,
            'system' => $this->system,
        ];
    }

    /** @param  array<string, mixed>  $snapshot */
    public static function fromSnapshot(array $snapshot): self
    {
        // Backwards-compat: oude snapshots hadden gescheiden fields+variables.
        $values = array_replace(
            self::arrayFrom($snapshot, 'fields'),
            self::arrayFrom($snapshot, 'variables'),
            self::arrayFrom($snapshot, 'values'),
        );

        return new self(
            values: $values,
            system: self::arrayFrom($snapshot, 'system'),
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
}
