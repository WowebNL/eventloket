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
            'map' => $this->renderCallChain($pad, 'Map', $key, $label, $component),
            'addressNL' => $this->renderAddressNL($component, $pad),
            'fieldset' => $this->renderContainer($component, $pad, 'Fieldset', $label ?: $key),
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
            $chain .= "\n{$pad}    ->label('{$this->esc($label)}')";
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
            $chain .= "\n{$pad}    ->label('{$this->esc($label)}')";
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
            $chain .= "\n{$pad}    ->label('{$this->esc($label)}')";
        }
        $chain .= $this->renderOptionsBlock($component, $pad);
        $chain .= $this->commonModifiers($component, $pad);

        return $chain;
    }

    /** @param  array<string, mixed>  $component */
    private function renderOptionsBlock(array $component, string $pad): string
    {
        $values = $component['values'] ?? null;
        if (! is_array($values) || $values === []) {
            $data = $component['data']['values'] ?? null;
            if (is_array($data)) {
                $values = $data;
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
            ."{$pad}    ->schema([\n{$body}\n{$pad}    ])";
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
            ."{$pad}    ->schema([\n{$body}\n{$pad}    ])";
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

        return $chain;
    }

    /** @param  array<string, mixed>  $component */
    private function renderAddressNL(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? '');
        $label = (string) ($component['label'] ?? '');
        $labelArg = $label !== '' ? ", '{$this->esc($label)}'" : '';

        return "{$pad}AddressNL::make('{$this->esc($key)}'{$labelArg})";
    }

    /** @param  array<string, mixed>  $component */
    private function renderContent(array $component, string $pad): string
    {
        $key = (string) ($component['key'] ?? 'content');
        $html = (string) ($component['html'] ?? '');
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $html);

        return "{$pad}Placeholder::make('{$this->esc($key)}')\n"
            ."{$pad}    ->content(new \\Illuminate\\Support\\HtmlString('{$escaped}'))";
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

        return $chain;
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
        use Filament\\Forms\\Components\\Placeholder;
        use Filament\\Forms\\Components\\Radio;
        use Filament\\Forms\\Components\\Repeater;
        use Filament\\Forms\\Components\\Select;
        use Filament\\Forms\\Components\\Textarea;
        use Filament\\Forms\\Components\\TextInput;
        use Filament\\Schemas\\Components\\Fieldset;
        use Filament\\Schemas\\Components\\Grid;
        use Filament\\Schemas\\Components\\Wizard\\Step;

        /**
         * @openforms-step-uuid {$uuid}
         * @openforms-step-index {$index}
         */
        final class {$className}
        {
            public static function make(): Step
            {
                return Step::make('{$stepLabel}')
                    ->schema([
        {$schemaBody}
                    ]);
            }
        }

        PHP;
    }
}
