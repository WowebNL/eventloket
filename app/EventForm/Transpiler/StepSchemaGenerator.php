<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

use RuntimeException;

/**
 * Genereert per form-step een complete Filament Step-klasse.
 *
 * Component-types worden 1-op-1 gemapt naar Filament-velden:
 *  - textfield/email/phoneNumber/number/currency → TextInput (met modifiers)
 *  - textarea → Textarea
 *  - radio/select → Radio / Select met options
 *  - selectboxes → CheckboxList
 *  - datetime/date → DateTimePicker / DatePicker
 *  - file → FileUpload
 *  - map → Dotswan\MapPicker\Fields\Map
 *  - addressNL → \App\EventForm\Components\AddressNL (fase 2 custom; voor
 *    nu een gewone TextInput-placeholder totdat de component er is)
 *  - fieldset → Fieldset met nested schema
 *  - columns → Grid::make(N) met nested schema
 *  - editgrid → Repeater met nested schema
 *  - content → Placeholder met HtmlString body
 */
class StepSchemaGenerator
{
    /** @var array<string, string> fieldKey → type (globale index over alle stappen) */
    private array $fieldTypeIndex = [];

    /** @var array<string, true> keys die als conditional.when trigger dienen */
    private array $triggerKeys = [];

    /** @var array<string, mixed> variable-key → initial_value, voor options-emit */
    private array $variableInitialValues = [];

    /**
     * Geef de generator een globale mapping van veld-key → type zodat
     * conditional-emissie correct `$get(key.subkey)` gebruikt voor
     * selectboxes-targets.
     *
     * @param  array<string, string>  $index
     */
    public function withFieldTypeIndex(array $index): self
    {
        $this->fieldTypeIndex = $index;

        return $this;
    }

    /**
     * Geef de set keys door die als trigger voor `conditional.when` dienen.
     * Die velden krijgen een `->live()` modifier zodat Filament hun
     * visibility-closures bij elke state-change opnieuw evalueert.
     *
     * @param  list<string>  $keys
     */
    public function withTriggerKeys(array $keys): self
    {
        $this->triggerKeys = array_fill_keys($keys, true);

        return $this;
    }

    /**
     * Voorzie de generator van initial-values van form-variables, zodat
     * select/radio/checkboxlist-velden met `openForms.dataSrc = "variable"`
     * hun opties kunnen emitten op basis van `itemsExpression.var`.
     *
     * @param  array<string, mixed>  $values  varKey → initial_value
     */
    public function withVariableInitialValues(array $values): self
    {
        $this->variableInitialValues = $values;

        return $this;
    }

    /** @param  array<string, mixed>  $step */
    public function generate(array $step): GeneratedStep
    {
        $uuid = (string) ($step['uuid'] ?? '');
        $slug = (string) ($step['slug'] ?? '');
        $name = (string) ($step['name'] ?? $slug);
        $index = is_int($step['index'] ?? null) ? $step['index'] : 0;

        if ($slug === '' && $name === '') {
            throw new RuntimeException('Step requires either slug or name');
        }

        $className = $this->buildClassName($slug !== '' ? $slug : $name);
        $components = $step['configuration']['components'] ?? [];
        if (! is_array($components)) {
            $components = [];
        }

        /** @var list<array<string, mixed>> $components */

        // Fallback: als er geen globale fieldTypeIndex is gezet, bouwen we
        // per-step een lokale index zodat in-step selectboxes-conditionals
        // correct emitten.
        if ($this->fieldTypeIndex === []) {
            $this->indexComponentsLocal($components);
        }

        $schemaBody = $this->renderComponents($components, indent: 16);

        $fileContent = $this->renderClassFile($className, $name, $uuid, $index, $schemaBody);

        return new GeneratedStep($className, $fileContent, $uuid, $index);
    }

    /** @param  list<array<string, mixed>>  $components */
    private function renderComponents(array $components, int $indent): string
    {
        $lines = [];
        foreach ($components as $component) {
            $rendered = $this->renderComponent($component, $indent);
            if ($rendered !== null) {
                $lines[] = $rendered;
            }
        }

        return implode(",\n", $lines);
    }

    /** @param  array<string, mixed>  $component */
    private function renderComponent(array $component, int $indent): ?string
    {
        $type = (string) ($component['type'] ?? '');
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $pad = str_repeat(' ', $indent);

        return match ($type) {
            'textfield' => $this->renderTextInput($component, $pad),
            'textarea' => $this->renderCallChain($pad, 'Textarea', $key, $label, $component),
            'email' => $this->renderTextInput($component, $pad, extra: '->email()'),
            'phoneNumber' => $this->renderTextInput($component, $pad, extra: '->tel()'),
            'number' => $this->renderTextInput($component, $pad, extra: '->numeric()'),
            'currency' => $this->renderTextInput($component, $pad, extra: "->numeric()->prefix('€')"),
            'date' => $this->renderCallChain($pad, 'DatePicker', $key, $label, $component),
            'datetime' => $this->renderCallChain($pad, 'DateTimePicker', $key, $label, $component),
            'radio' => $this->renderWithOptions($pad, 'Radio', $component),
            'select' => $this->renderWithOptions($pad, 'Select', $component),
            'selectboxes' => $this->renderWithOptions($pad, 'CheckboxList', $component),
            'file' => $this->renderCallChain($pad, 'FileUpload', $key, $label, $component),
            'map' => $this->renderMap($component, $pad),
            'addressNL' => $this->renderAddressNL($component, $pad),
            'fieldset' => $this->renderFieldset($component, $pad, $label ?: $key),
            'columns' => $this->renderColumns($component, $pad),
            'editgrid' => $this->renderRepeater($component, $pad),
            'content' => $this->renderContent($component, $pad),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $component */
    private function renderTextInput(array $component, string $pad, string $extra = ''): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $chain = "{$pad}TextInput::make('{$this->esc($key)}')";
        if ($label !== '') {
            $chain .= "\n{$pad}    ".$this->labelModifier($label, $pad);
        }
        if ($extra !== '') {
            $chain .= "\n{$pad}    {$extra}";
        }
        $chain .= $this->commonModifiers($component, $pad);

        return $chain;
    }

    /** @param  array<string, mixed>  $component */
    private function renderCallChain(string $pad, string $class, string $key, string $label, array $component): string
    {
        $chain = "{$pad}{$class}::make('{$this->esc($key)}')";
        if ($label !== '') {
            $chain .= "\n{$pad}    ".$this->labelModifier($label, $pad);
        }
        $chain .= $this->commonModifiers($component, $pad);

        return $chain;
    }

    /** @param  array<string, mixed>  $component */
    private function renderWithOptions(string $pad, string $class, array $component): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $chain = "{$pad}{$class}::make('{$this->esc($key)}')";
        if ($label !== '') {
            $chain .= "\n{$pad}    ".$this->labelModifier($label, $pad);
        }
        $chain .= $this->renderOptionsBlock($component, $pad);
        $chain .= $this->commonModifiers($component, $pad);

        return $chain;
    }

    /**
     * Emit `->label(...)`. Labels met `{{ var }}` krijgen een closure die
     * de Livewire-state op render-tijd oplost via LabelRenderer. Statische
     * labels krijgen een simpele string-vorm.
     */
    private function labelModifier(string $label, string $pad): string
    {
        if (! str_contains($label, '{{')) {
            return "->label('{$this->esc($label)}')";
        }

        // `$livewire` is een Filament parameter die de hostende Page injecteert;
        // die heeft een `state()` accessor naar de FormState.
        return '->label(fn ($livewire): string => app(\\App\\EventForm\\Template\\LabelRenderer::class)'
            ."->render('{$this->esc($label)}', \$livewire->state()))";
    }

    /** @param  array<string, mixed>  $component */
    private function renderOptionsBlock(array $component, string $pad): string
    {
        $values = $component['values'] ?? null;

        // Lege/placeholder values-lijst (bij OF de `[{label:'',value:''}]`
        // default) → probeer `data.values[]` (select met inline lijst) of
        // `openForms.dataSrc === 'variable'` (opties uit variable).
        if (! $this->isUsableValuesList($values)) {
            $dataValues = $component['data']['values'] ?? null;
            if ($this->isUsableValuesList($dataValues)) {
                $values = $dataValues;
            } else {
                $values = $this->resolveVariableBackedOptions($component);
            }
        }

        if (! is_array($values) || $values === []) {
            return '';
        }

        $pairs = [];
        $seen = [];
        foreach ($values as $v) {
            if (! is_array($v)) {
                continue;
            }
            $val = (string) ($v['value'] ?? '');
            $lbl = (string) ($v['label'] ?? '');
            if ($val === '') {
                continue;
            }
            // Dedupe keys: OF staat dubbele values toe (scores in de risicoscan);
            // Filament moet unieke option-keys hebben. Hang een `__N` aan als we
            // een duplicaat tegenkomen — de rule-triggers gebruiken niet de
            // opties-key dus dit heeft geen gedragsgevolg.
            if (isset($seen[$val])) {
                $seen[$val]++;
                $val = $val.'__'.$seen[$val];
            } else {
                $seen[$val] = 1;
            }
            $pairs[] = "{$pad}        '{$this->esc($val)}' => '{$this->esc($lbl)}'";
        }
        if ($pairs === []) {
            return '';
        }

        return "\n{$pad}    ->options([\n".implode(",\n", $pairs)."\n{$pad}    ])";
    }

    /**
     * OF gebruikt lege fieldsets als inline section-header (alleen een
     * legend-label, geen schema). Filament Fieldset::make() eist een
     * niet-leeg schema. Zonder children slaan we deze dus over — de
     * volgende velden (die vaak top-level met eigen visibility-rules
     * staan) vervullen de echte inhoudelijke rol.
     *
     * @param  array<string, mixed>  $component
     */
    private function renderFieldset(array $component, string $pad, string $label): ?string
    {
        $inner = $component['components'] ?? [];
        $hasChildren = is_array($inner) && array_filter($inner, static fn ($c): bool => is_array($c)) !== [];
        if (! $hasChildren) {
            return null;
        }

        return $this->renderContainer($component, $pad, 'Fieldset', $label);
    }

    /** @param  array<string, mixed>  $component */
    private function renderContainer(array $component, string $pad, string $class, string $label): string
    {
        $inner = $component['components'] ?? [];
        if (! is_array($inner)) {
            $inner = [];
        }
        /** @var list<array<string, mixed>> $inner */
        $body = $this->renderComponents($inner, indent: strlen($pad) + 8);
        $labelArg = $label !== '' ? "'{$this->esc($label)}'" : "''";

        return "{$pad}{$class}::make({$labelArg})\n"
            ."{$pad}    ->schema([\n{$body}\n{$pad}    ])"
            .$this->visibilityModifiers($component, $pad);
    }

    /** @param  array<string, mixed>  $component */
    private function renderColumns(array $component, string $pad): string
    {
        $columns = $component['columns'] ?? [];
        if (! is_array($columns)) {
            $columns = [];
        }
        $count = count($columns);
        $allInner = [];
        foreach ($columns as $col) {
            if (is_array($col) && is_array($col['components'] ?? null)) {
                /** @var list<array<string, mixed>> $inner */
                $inner = $col['components'];
                foreach ($inner as $child) {
                    $allInner[] = $child;
                }
            }
        }
        $body = $this->renderComponents($allInner, indent: strlen($pad) + 8);

        return "{$pad}Grid::make({$count})\n"
            ."{$pad}    ->schema([\n{$body}\n{$pad}    ])"
            .$this->visibilityModifiers($component, $pad);
    }

    /** @param  array<string, mixed>  $component */
    private function renderRepeater(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $inner = $component['components'] ?? [];
        if (! is_array($inner)) {
            $inner = [];
        }
        /** @var list<array<string, mixed>> $inner */
        $body = $this->renderComponents($inner, indent: strlen($pad) + 8);

        $chain = "{$pad}Repeater::make('{$this->esc($key)}')";
        if ($label !== '') {
            $chain .= "\n{$pad}    ->label('{$this->esc($label)}')";
        }
        $chain .= "\n{$pad}    ->schema([\n{$body}\n{$pad}    ])";
        $chain .= $this->visibilityModifiers($component, $pad);

        return $chain;
    }

    /**
     * Emit een Dotswan Map-veld met interactie-type uit OF's `component.interactions`.
     * Defaults naar Zuid-Limburg (Maastricht) als startpositie; GeoMan is
     * altijd aan zodat de user kan tekenen/bewerken.
     *
     * @param  array<string, mixed>  $component
     */
    private function renderMap(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $interactions = is_array($component['interactions'] ?? null) ? $component['interactions'] : [];
        $allowPolygon = (bool) ($interactions['polygon'] ?? false);
        $allowPolyline = (bool) ($interactions['polyline'] ?? false);
        $allowMarker = (bool) ($interactions['marker'] ?? false);

        // Als er geen interactions-config is, defaulten we naar marker-only
        // (OF-gedrag voor "punt-selectie" velden).
        if (! $allowPolygon && ! $allowPolyline && ! $allowMarker) {
            $allowMarker = true;
        }

        $chain = "{$pad}Map::make('{$this->esc($key)}')";
        if ($label !== '') {
            $chain .= "\n{$pad}    ".$this->labelModifier($label, $pad);
        }

        // Default: Maastricht (Veiligheidsregio Zuid-Limburg).
        $chain .= "\n{$pad}    ->defaultLocation(50.8514, 5.6910)";
        $chain .= "\n{$pad}    ->zoom(11)";
        $chain .= "\n{$pad}    ->geoMan(true)";
        $chain .= "\n{$pad}    ->geoManEditable(true)";
        $chain .= "\n{$pad}    ->drawPolygon(".($allowPolygon ? 'true' : 'false').')';
        $chain .= "\n{$pad}    ->drawPolyline(".($allowPolyline ? 'true' : 'false').')';
        $chain .= "\n{$pad}    ->drawMarker(".($allowMarker ? 'true' : 'false').')';
        $chain .= "\n{$pad}    ->drawCircle(false)";
        $chain .= "\n{$pad}    ->drawRectangle(false)";
        // Zonder min-height renderd Leaflet in een 0px container en blijft
        // de kaart onzichtbaar. Ook columnSpanFull zodat de kaart de hele
        // breedte benut in Repeater-rows en Fieldsets.
        $chain .= "\n{$pad}    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])";
        $chain .= "\n{$pad}    ->columnSpanFull()";
        $chain .= $this->commonModifiers($component, $pad);

        return $chain;
    }

    /** @param  array<string, mixed>  $component */
    private function renderAddressNL(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $labelArg = $label !== '' ? ", '{$this->esc($label)}'" : '';

        return "{$pad}AddressNL::make('{$this->esc($key)}'{$labelArg})"
            .$this->visibilityModifiers($component, $pad);
    }

    /** @param  array<string, mixed>  $component */
    private function renderContent(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? 'content');
        $html = $this->stripInlineColors((string) ($component['html'] ?? ''));
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $html);

        // Content-HTML kan `{{ var }}` en `{% get_value ... %}` bevatten; die
        // moeten door LabelRenderer voordat Filament ze rendert. De closure
        // resolved ze bij elke render tegen de huidige FormState.
        return "{$pad}TextEntry::make('{$this->esc($key)}')\n"
            ."{$pad}    ->hiddenLabel()\n"
            ."{$pad}    ->state(fn (\$livewire) => new \\Illuminate\\Support\\HtmlString("
            ."app(\\App\\EventForm\\Template\\LabelRenderer::class)->render('{$escaped}', \$livewire->state())))"
            .$this->visibilityModifiers($component, $pad);
    }

    /**
     * Detecteert de "lege placeholder" die OF vaak laat staan
     * (`[{label:'', value:''}]`) als legitieme options.
     *
     * @param  mixed  $values
     */
    private function isUsableValuesList(mixed $values): bool
    {
        if (! is_array($values) || $values === []) {
            return false;
        }
        foreach ($values as $v) {
            if (is_array($v) && isset($v['value']) && is_string($v['value']) && $v['value'] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve options voor een component dat z'n lijst uit een form-variable
     * trekt (OF's `openForms.dataSrc === 'variable'`). Emit een list<value,label>
     * passend bij `renderOptionsBlock`.
     *
     * @param  array<string, mixed>  $component
     * @return list<array{value: string, label: string}>|null
     */
    private function resolveVariableBackedOptions(array $component): ?array
    {
        $openForms = $component['openForms'] ?? null;
        if (! is_array($openForms) || ($openForms['dataSrc'] ?? null) !== 'variable') {
            return null;
        }
        $expr = $openForms['itemsExpression'] ?? null;
        if (! is_array($expr) || ! is_string($expr['var'] ?? null)) {
            return null;
        }
        $varName = $expr['var'];
        if (! array_key_exists($varName, $this->variableInitialValues)) {
            return null;
        }
        $initial = $this->variableInitialValues[$varName];
        if (! is_array($initial) || $initial === []) {
            return null;
        }

        $result = [];
        foreach ($initial as $entry) {
            if (is_string($entry)) {
                // Flat string-array ("Ja", "Nee", ... / event types)
                $result[] = ['value' => $entry, 'label' => $entry];
            } elseif (is_array($entry) && count($entry) >= 2) {
                // Nested [code, label]-pair (zoals voorwerpenLijst).
                $v = $entry[0] ?? null;
                $l = $entry[1] ?? null;
                if (is_string($v) && is_string($l)) {
                    $result[] = ['value' => $v, 'label' => $l];
                }
            }
        }

        return $result === [] ? null : $result;
    }

    /** @param  list<array<string, mixed>>  $components */
    private function indexComponentsLocal(array $components): void
    {
        foreach ($components as $component) {
            $key = $component['key'] ?? null;
            $type = $component['type'] ?? null;
            if (is_string($key) && is_string($type) && $key !== '') {
                $this->fieldTypeIndex[$key] = $type;
            }
            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->indexComponentsLocal($nested);
            }
            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->indexComponentsLocal($nested);
                    }
                }
            }
        }
    }

    /** @param  array<string, mixed>  $component */
    private function commonModifiers(array $component, string $pad): string
    {
        $chain = '';
        $validate = is_array($component['validate'] ?? null) ? $component['validate'] : [];
        if (! empty($validate['required'])) {
            $chain .= "\n{$pad}    ->required()";
        }
        if (isset($validate['maxLength']) && is_numeric($validate['maxLength'])) {
            $chain .= "\n{$pad}    ->maxLength({$validate['maxLength']})";
        }
        if (isset($validate['minLength']) && is_numeric($validate['minLength'])) {
            $chain .= "\n{$pad}    ->minLength({$validate['minLength']})";
        }
        $chain .= $this->visibilityModifiers($component, $pad);
        $chain .= $this->liveModifier($component, $pad);

        return $chain;
    }

    /**
     * Emit `->live()` op velden die elders als trigger voor een
     * `conditional.when` worden gebruikt. Zonder `->live()` herberekent
     * Filament de `visible()`-closures niet bij state-changes.
     *
     * @param  array<string, mixed>  $component
     */
    private function liveModifier(array $component, string $pad): string
    {
        if ($this->triggerKeys === []) {
            // Auto-detectie: als de fieldTypeIndex gevuld is (runtime scan
            // binnen één step), zijn er geen trigger-keys bekend; in dat
            // geval ook niets emitten.
            return '';
        }
        $key = $component['key'] ?? null;
        if (! is_string($key) || ! isset($this->triggerKeys[$key])) {
            return '';
        }

        return "\n{$pad}    ->live()";
    }

    /**
     * Emit één gecombineerde `->hidden(fn)` closure die drie zichtbaarheids-
     * bronnen respecteert in deze volgorde van prioriteit:
     *
     * 1. **Rule-driven override**: `FormState::isFieldHidden($key)` — een rule
     *    die `setFieldHidden('X', true|false)` aanroept heeft vetorecht.
     * 2. **OF default** (`component.hidden = true`): het veld is initieel
     *    verborgen tenzij een rule 'm expliciet op zichtbaar zet.
     * 3. **Directe `conditional.show/when/eq`** op het component: show-match
     *    of hide-match vertaalt naar een `$get`-closure. Selectboxes-targets
     *    gebruiken `in_array` vanwege Filament's array-state-formaat.
     *
     * Filament's `->hidden()` wint van `->visible()`, dus we emitten altijd
     * één `->hidden(fn)`.
     *
     * @param  array<string, mixed>  $component
     */
    private function visibilityModifiers(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? '');
        $defaultHidden = (bool) ($component['hidden'] ?? false);
        $conditionalTest = $this->conditionalTest($component);

        if (! $defaultHidden && $conditionalTest === null) {
            return '';
        }

        $keyLiteral = "'".$this->esc($key)."'";
        $indent = $pad.'    ';

        // Default-hidden in OF betekent "altijd verborgen, behalve als een
        // rule het expliciet op false zet". De directe conditional is in dit
        // geval irrelevant: de rule wint. Emit een minimale closure.
        if ($defaultHidden) {
            return "\n{$indent}->hidden(fn (\$livewire): bool => "
                ."\$livewire->state()->isFieldHidden({$keyLiteral}) !== false)";
        }

        // Geen default-hidden: de conditional bepaalt de zichtbaarheid, met
        // een optionele rule-override die wint als die expliciet true/false
        // heeft gezet.
        $body = $indent.'    ';
        $lines = [
            '->hidden(function (\Filament\Schemas\Components\Utilities\Get $get, $livewire): bool {',
            "{$body}\$rule = \$livewire->state()->isFieldHidden({$keyLiteral});",
            "{$body}if (\$rule !== null) {",
            "{$body}    return \$rule;",
            "{$body}}",
            '',
            "{$body}return {$conditionalTest};",
            "{$indent}})",
        ];

        return "\n{$indent}".implode("\n", $lines);
    }

    /**
     * Bouw de PHP-expressie die bepaalt of een veld verborgen moet zijn op
     * basis van `conditional.show/when/eq`, of null als er geen conditional is.
     *
     * @param  array<string, mixed>  $component
     */
    private function conditionalTest(array $component): ?string
    {
        $conditional = $component['conditional'] ?? null;
        if (! is_array($conditional)) {
            return null;
        }
        $show = $conditional['show'] ?? null;
        $when = $conditional['when'] ?? null;
        $eq = $conditional['eq'] ?? '';
        if (! is_bool($show) || ! is_string($when) || $when === '') {
            return null;
        }

        $targetType = $this->fieldTypeIndex[$when] ?? null;
        if ($targetType === 'selectboxes') {
            // Filament's CheckboxList bewaart state als `['key1', 'key2']` —
            // een vlakke array van geselecteerde values.
            $match = "in_array('".$this->esc((string) $eq)."', (array) \$get('".$this->esc($when)."'), true)";
        } else {
            $eqString = is_scalar($eq) ? (string) $eq : '';
            $match = "\$get('".$this->esc($when)."') === '".$this->esc($eqString)."'";
        }

        // show=true → verberg als geen match; show=false → verberg bij match.
        // Haakjes rond $match zijn essentieel omdat `!` strakker bindt dan
        // `===` — zonder haakjes wordt `! $get(...) === 'Y'` een bool-vs-string
        // vergelijking die altijd false oplevert.
        return $show === true ? "! ({$match})" : $match;
    }

    private function buildClassName(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii === false) {
            $ascii = $value;
        }
        $parts = preg_split('/[^a-zA-Z0-9]+/', $ascii) ?: [];
        $parts = array_values(array_filter($parts, static fn ($p): bool => $p !== ''));

        if ($parts === []) {
            return 'UnnamedStep';
        }

        $name = implode('', array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts));
        if (! str_ends_with($name, 'Step')) {
            $name .= 'Step';
        }

        if (! preg_match('/^[A-Za-z]/', $name)) {
            $name = 'Step'.$name;
        }

        return $name;
    }

    /**
     * OF's content-HTML komt uit een WYSIWYG-editor die `color:rgb(X,Y,Z)`
     * en `background-color:...` hard-coded in `style`-attributen zet.
     * Daardoor respecteert de tekst niet de dark/light-mode van de shell.
     * We strippen deze kleur-regels zodat `color` van de parent geërfd
     * wordt. Andere style-properties blijven intact.
     */
    private function stripInlineColors(string $html): string
    {
        return (string) preg_replace_callback(
            '/\bstyle="([^"]*)"/i',
            function (array $m): string {
                $style = (string) $m[1];
                $style = (string) preg_replace('/(?:^|;)\s*(?:background-)?color\s*:[^;]*;?/i', '', $style);
                $style = trim($style, "; \t");
                if ($style === '') {
                    return '';
                }

                return 'style="'.$style.'"';
            },
            $html,
        );
    }

    /**
     * Escape voor gebruik binnen een reeds quote-omsloten template
     * (`'{$this->esc(...)}'`). Verdubbelt backslashes en escapeet quotes in
     * de juiste volgorde.
     */
    private function esc(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);

        return str_replace("'", "\\'", $value);
    }

    private function renderClassFile(
        string $className,
        string $stepName,
        string $uuid,
        int $index,
        string $schemaBody,
    ): string {
        $stepLabel = $this->esc($stepName);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\EventForm\\Schema\\Steps;

        use App\\EventForm\\Components\\AddressNL;
        use Dotswan\\MapPicker\\Fields\\Map;
        use Filament\\Forms\\Components\\CheckboxList;
        use Filament\\Forms\\Components\\DatePicker;
        use Filament\\Forms\\Components\\DateTimePicker;
        use Filament\\Forms\\Components\\FileUpload;
        use Filament\\Forms\\Components\\Radio;
        use Filament\\Forms\\Components\\Repeater;
        use Filament\\Forms\\Components\\Select;
        use Filament\\Forms\\Components\\Textarea;
        use Filament\\Forms\\Components\\TextInput;
        use Filament\\Infolists\\Components\\TextEntry;
        use Filament\\Schemas\\Components\\Fieldset;
        use Filament\\Schemas\\Components\\Grid;
        use Filament\\Schemas\\Components\\Wizard\\Step;

        /**
         * @openforms-step-uuid {$uuid}
         * @openforms-step-index {$index}
         */
        final class {$className}
        {
            public const UUID = '{$uuid}';

            public static function make(): Step
            {
                return Step::make('{$stepLabel}')
                    ->key(self::UUID)
                    ->schema([
        {$schemaBody}
                    ]);
            }
        }

        PHP;
    }
}
