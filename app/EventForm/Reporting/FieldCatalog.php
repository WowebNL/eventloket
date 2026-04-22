<?php

declare(strict_types=1);

namespace App\EventForm\Reporting;

/**
 * Leest de Open Forms JSON-dump (docker/local-data/open-formulier/) en
 * biedt mens-leesbare lookups voor het gedragsspecificatie-rapport:
 *
 *  - op welke pagina staat een veld?
 *  - wat is de originele UI-label van een veld?
 *  - wat is de label van een optie-waarde (bv. 'A3' → 'Bouwsels groter dan 10m²')?
 *
 * Dient puur voor rendering-doeleinden — geen runtime-afhankelijkheid voor
 * het formulier zelf. Als de OF-dump niet (meer) aanwezig is, vallen alle
 * lookups terug op de technische keys zodat het rapport blijft werken.
 */
final class FieldCatalog
{
    /** @var array<string, array{index: int, naam: string}> step-uuid → meta */
    private array $steps = [];

    /** @var array<string, array{step_uuid: string, type: string, label: string, values: array<string, string>}> field-key → meta */
    private array $fields = [];

    /** @var array<string, true> step-uuid → heeft minstens één logic-rule die de stap raakt */
    private array $stepsWithLogic = [];

    public static function fromLocalDump(?string $path = null): self
    {
        $catalog = new self;
        $path ??= base_path('docker/local-data/open-formulier');
        if (! is_dir($path)) {
            return $catalog;
        }

        $stepsFile = $path.'/formSteps.json';
        if (is_file($stepsFile)) {
            $raw = json_decode((string) file_get_contents($stepsFile), true);
            $items = is_array($raw) && isset($raw['results']) ? $raw['results'] : $raw;
            if (is_array($items)) {
                $catalog->loadSteps($items);
            }
        }

        $logicFile = $path.'/formLogic.json';
        if (is_file($logicFile)) {
            $raw = json_decode((string) file_get_contents($logicFile), true);
            $items = is_array($raw) && isset($raw['results']) ? $raw['results'] : $raw;
            if (is_array($items)) {
                $catalog->loadLogic($items);
            }
        }

        return $catalog;
    }

    /**
     * Markeer welke stappen door de OF-logica geraakt worden — ofwel omdat
     * hun veld als action-target voorkomt, ofwel omdat hun UUID als
     * `form_step_uuid` in een step-applicable-actie zit, ofwel omdat een
     * veld op de stap als trigger-variable wordt gelezen.
     *
     * @param  list<array<string, mixed>>  $rules
     */
    private function loadLogic(array $rules): void
    {
        $fieldToStep = [];
        foreach ($this->fields as $key => $meta) {
            $fieldToStep[$key] = $meta['step_uuid'];
        }

        foreach ($rules as $rule) {
            $actions = $rule['actions'] ?? [];
            if (is_array($actions)) {
                foreach ($actions as $a) {
                    if (! is_array($a)) {
                        continue;
                    }
                    $target = (string) ($a['component'] ?? '');
                    if ($target !== '' && isset($fieldToStep[$target])) {
                        $this->stepsWithLogic[$fieldToStep[$target]] = true;
                    }
                    $targetStep = (string) ($a['form_step_uuid'] ?? '');
                    if ($targetStep !== '') {
                        $this->stepsWithLogic[$targetStep] = true;
                    }
                }
            }

            // Trigger-kant laten we bewust buiten beschouwing: velden die
            // alléén als template-variabele in labels/teksten voorkomen
            // ("Hallo {{ naam }}") leveren geen rule-driven gedrag op de
            // eigen stap op — de actie ligt elders. Alleen stappen met
            // action-effect tellen als "heeft dynamisch gedrag".
        }
    }

    /**
     * Heeft deze stap rule-driven gedrag (veld-zichtbaarheid, stap-applicability,
     * of variabele-mutaties)? Stappen zonder dit gedrag zijn puur statische
     * input-pagina's — hun inhoud verandert niet afhankelijk van eerdere
     * antwoorden.
     */
    public function stepHasLogic(string $uuid): bool
    {
        return isset($this->stepsWithLogic[$uuid]);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function loadSteps(array $steps): void
    {
        foreach ($steps as $step) {
            $uuid = (string) ($step['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $this->steps[$uuid] = [
                'index' => (int) ($step['index'] ?? count($this->steps)),
                'naam' => (string) ($step['name'] ?? 'Onbekende stap'),
            ];

            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                $this->walkComponents($components, $uuid);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function walkComponents(array $components, string $stepUuid): void
    {
        foreach ($components as $component) {
            $key = (string) ($component['key'] ?? '');
            $type = (string) ($component['type'] ?? '');
            $label = (string) ($component['label'] ?? '');

            if ($key !== '') {
                $this->fields[$key] = [
                    'step_uuid' => $stepUuid,
                    'type' => $type,
                    // OF-labels bevatten vaak HTML-entities (`&gt;`, `&amp;`) en
                    // soms inline <sup>/<strong>. Voor leesbaarheid in het
                    // rapport decoden we entities; tags laten we staan (GitHub
                    // markdown rendert inline HTML prima).
                    'label' => html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'values' => $this->extractValues($component),
                ];
            }

            // Component-level conditional: `{conditional: {show: true, when: "anderVeld", eq: "waarde"}}`
            // markeert dat dit component voorwaardelijk getoond wordt op basis
            // van een ander veld. Dat is ook "dynamisch gedrag" en telt mee,
            // ook al gaat het niet via een logic-rule.
            $conditional = $component['conditional'] ?? null;
            if (is_array($conditional) && ! empty($conditional['when'])) {
                $this->stepsWithLogic[$stepUuid] = true;
            }

            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->walkComponents($nested, $stepUuid);
            }
            if ($type === 'columns' && isset($component['columns']) && is_array($component['columns'])) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && isset($column['components']) && is_array($column['components'])) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->walkComponents($nested, $stepUuid);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $component
     * @return array<string, string>  value → label
     */
    private function extractValues(array $component): array
    {
        $out = [];
        // Selectboxes/radio/select gebruiken allemaal `values`
        $values = $component['values'] ?? null;
        if (is_array($values)) {
            foreach ($values as $v) {
                if (is_array($v) && isset($v['value']) && isset($v['label'])) {
                    $out[(string) $v['value']] = (string) $v['label'];
                }
            }
        }
        // Select-type component kan `data.values` gebruiken
        $dataValues = $component['data']['values'] ?? null;
        if (is_array($dataValues)) {
            foreach ($dataValues as $v) {
                if (is_array($v) && isset($v['value']) && isset($v['label'])) {
                    $out[(string) $v['value']] = (string) $v['label'];
                }
            }
        }

        return $out;
    }

    public function stepLabel(string $uuid): ?string
    {
        $step = $this->steps[$uuid] ?? null;
        if ($step === null) {
            return null;
        }

        return sprintf('Stap %d: %s', $step['index'] + 1, $step['naam']);
    }

    public function stepIndex(string $uuid): ?int
    {
        return $this->steps[$uuid]['index'] ?? null;
    }

    /**
     * @return array<string, array{index: int, naam: string}>  uuid → meta, gesorteerd op index
     */
    public function allSteps(): array
    {
        $sorted = $this->steps;
        uasort($sorted, static fn ($a, $b) => $a['index'] <=> $b['index']);

        return $sorted;
    }

    /**
     * Label van een veld. Ondersteunt dot-paden voor subvelden zoals
     * `evenementInGemeente.brk_identification`: we pakken de root-key.
     */
    public function fieldLabel(string $key): ?string
    {
        $root = explode('.', $key)[0];

        return $this->fields[$root]['label'] ?? null;
    }

    public function fieldStep(string $key): ?string
    {
        $root = explode('.', $key)[0];

        return $this->fields[$root]['step_uuid'] ?? null;
    }

    public function fieldType(string $key): ?string
    {
        $root = explode('.', $key)[0];

        return $this->fields[$root]['type'] ?? null;
    }

    public function optionLabel(string $fieldKey, string $optionValue): ?string
    {
        $root = explode('.', $fieldKey)[0];

        return $this->fields[$root]['values'][$optionValue] ?? null;
    }

    public function hasField(string $key): bool
    {
        $root = explode('.', $key)[0];

        return isset($this->fields[$root]);
    }
}
