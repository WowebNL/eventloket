<?php

declare(strict_types=1);

namespace App\EventForm\Reporting;

use App\EventForm\State\FormState;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard\Step;
use ReflectionObject;

/**
 * Bouwt het inzendingsbewijs als een lijst secties (één per stap)
 * met (label, waarde)-paren. Stappen die geen ingevulde velden
 * bevatten worden weggelaten zodat de PDF compact blijft.
 *
 * Werkwijze: per stap walken we via reflection door de child-
 * components van de Filament-Step, omdat Filament's eigen
 * `getChildComponents()` een container-context nodig heeft die we
 * in de queue-job (zonder mounted Livewire-component) niet hebben.
 *
 * Labels zijn vaak Closures die `$livewire->state()` gebruiken om
 * placeholders zoals `{{ watIsDeNaamVanHetEvenementVergunning }}`
 * te interpoleren. We voeden ze met een mini-stub die diezelfde
 * `state()`-methode aanbiedt — dat is exact wat Filament's eigen
 * runtime ook doet, alleen via z'n parameter-injectie.
 */
final class SubmissionReport
{
    /**
     * @param  list<Step>  $steps
     * @return list<array{title: string, entries: list<array{label: string, value: string}>}>
     */
    public function build(FormState $state, array $steps): array
    {
        $sections = [];

        foreach ($steps as $step) {
            $entries = $this->extractEntries($step, $state);
            if ($entries === []) {
                continue;
            }
            $title = (string) ($step->getLabel() ?? '');
            $sections[] = [
                'title' => $title !== '' ? $title : 'Sectie',
                'entries' => $entries,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function extractEntries(Step $step, FormState $state): array
    {
        $stubLivewire = $this->stubLivewire($state);
        $entries = [];

        $walk = function (object $component) use (&$walk, &$entries, $state, $stubLivewire): void {
            if ($component instanceof Field && ! ($component instanceof Repeater)) {
                $key = $component->getName();
                if ($key !== '' && $key !== null) {
                    $value = $this->renderValue($component, $state, $key);
                    if ($value !== '') {
                        $entries[] = [
                            'label' => $this->renderLabel($component, $stubLivewire),
                            'value' => $value,
                        ];
                    }
                }
            }

            // Repeaters renderen we als één samenvatting-entry: "3 rij(en)"
            // — de individuele waarden zijn voor een PDF-overzicht zelden
            // nuttig en zouden de tabel onleesbaar maken.
            if ($component instanceof Repeater) {
                $key = $component->getName();
                $rows = is_array($state->get($key)) ? $state->get($key) : [];
                if (count($rows) > 0) {
                    $entries[] = [
                        'label' => $this->renderLabel($component, $stubLivewire),
                        'value' => count($rows).' rij(en) ingevuld',
                    ];
                }

                return; // niet verder afdalen — repeater-content is per rij
            }

            if (property_exists($component, 'childComponents')) {
                $reflection = new ReflectionObject($component);
                if ($reflection->hasProperty('childComponents')) {
                    $prop = $reflection->getProperty('childComponents');
                    $prop->setAccessible(true);
                    $children = $prop->getValue($component);
                    if (is_array($children)) {
                        foreach ($children as $list) {
                            if (! is_array($list)) {
                                continue;
                            }
                            foreach ($list as $child) {
                                if (is_object($child)) {
                                    $walk($child);
                                }
                            }
                        }
                    }
                }
            }
        };

        $walk($step);

        return $entries;
    }

    private function renderLabel(Field $component, object $stubLivewire): string
    {
        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('label')) {
            return $component->getName();
        }
        $prop = $reflection->getProperty('label');
        $prop->setAccessible(true);
        $raw = $prop->getValue($component);

        if ($raw instanceof Closure) {
            try {
                return (string) $raw($stubLivewire);
            } catch (\Throwable) {
                return $component->getName();
            }
        }

        return (string) ($raw ?? $component->getName());
    }

    private function renderValue(Field $component, FormState $state, string $key): string
    {
        $value = $state->get($key);

        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match (true) {
            $component instanceof DateTimePicker => $this->humanDateTime($value),
            $component instanceof DatePicker => $this->humanDate($value),
            $component instanceof CheckboxList => $this->renderList($value),
            $component instanceof Radio, $component instanceof Select => $this->renderSelectValue($component, $value),
            $component instanceof FileUpload => $this->renderFiles($value),
            $component instanceof Textarea, $component instanceof TextInput => (string) $value,
            default => is_scalar($value) ? (string) $value : $this->renderList($value),
        };
    }

    private function renderSelectValue(Field $component, mixed $value): string
    {
        // Probeer de option-label te tonen i.p.v. de raw key. We doen dat
        // via reflection op `$options` omdat `getOptions()` ook een
        // container nodig kan hebben.
        $reflection = new ReflectionObject($component);
        if ($reflection->hasProperty('options')) {
            $prop = $reflection->getProperty('options');
            $prop->setAccessible(true);
            $rawOptions = $prop->getValue($component);
            $options = $rawOptions instanceof Closure ? null : $rawOptions;
            if (is_array($options)) {
                if (is_array($value)) {
                    return collect($value)->map(fn ($v) => (string) ($options[$v] ?? $v))->implode(', ');
                }

                return (string) ($options[$value] ?? $value);
            }
        }

        return is_array($value) ? $this->renderList($value) : (string) $value;
    }

    /** @param  array<int|string, mixed>|string|int|float|bool  $value */
    private function renderList(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return collect($value)
            ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))
            ->filter()
            ->implode(', ');
    }

    private function renderFiles(mixed $value): string
    {
        if (! is_array($value)) {
            return is_string($value) ? basename($value) : '';
        }

        return collect($value)
            ->map(fn ($v) => is_string($v) ? basename($v) : (is_array($v) ? ($v['name'] ?? '') : ''))
            ->filter()
            ->implode(', ');
    }

    private function humanDateTime(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }
        try {
            return Carbon::parse((string) $value, 'Europe/Amsterdam')->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function humanDate(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }
        try {
            return Carbon::parse((string) $value, 'Europe/Amsterdam')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function stubLivewire(FormState $state): object
    {
        return new class($state)
        {
            public function __construct(private readonly FormState $state) {}

            public function state(): FormState
            {
                return $this->state;
            }
        };
    }
}
