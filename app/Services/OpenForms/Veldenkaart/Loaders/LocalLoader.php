<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Loaders;

use App\Services\OpenForms\Veldenkaart\Data\RawFormData;
use RuntimeException;

class LocalLoader implements LoaderInterface
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function load(string $formIdentifier): RawFormData
    {
        $forms = $this->readJsonArray('forms.json');
        $allSteps = $this->readJsonArray('formSteps.json');
        $allLogic = $this->readJsonArray('formLogic.json');
        $allVariables = $this->readJsonArray('formVariables.json');
        $meta = $this->readJsonObjectIfExists('_meta.json');

        $form = $this->findForm($forms, $formIdentifier);
        $formUuid = $this->extractString($form, 'uuid');
        $formUrl = $this->extractString($form, 'url');

        $stepUuids = $this->collectStepUuidsForForm($form);
        $formSteps = $this->filterStepsByUuids($allSteps, $stepUuids);
        $logicRules = $this->filterByFormReference($allLogic, $formUrl, $formUuid);
        $variables = $this->filterByFormReference($allVariables, $formUrl, $formUuid);

        return new RawFormData(
            form: $form,
            formSteps: $formSteps,
            logicRules: $logicRules,
            variables: $variables,
            meta: [
                'of_release' => is_string($meta['of_release'] ?? null) ? $meta['of_release'] : null,
                'of_git_sha' => is_string($meta['of_git_sha'] ?? null) ? $meta['of_git_sha'] : null,
                'source' => $this->sourceLabel(),
            ],
        );
    }

    public function sourceLabel(): string
    {
        return 'local:'.$this->directory;
    }

    /** @return list<array<string, mixed>> */
    private function readJsonArray(string $filename): array
    {
        $path = rtrim($this->directory, '/').'/'.$filename;
        if (! is_file($path)) {
            throw new RuntimeException("Missing OF dump file: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read file: {$path}");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Expected JSON array in {$path}");
        }

        /** @var list<array<string, mixed>> $result */
        $result = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function readJsonObjectIfExists(string $filename): array
    {
        $path = rtrim($this->directory, '/').'/'.$filename;
        if (! is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  list<array<string, mixed>>  $forms
     * @return array<string, mixed>
     */
    private function findForm(array $forms, string $identifier): array
    {
        foreach ($forms as $form) {
            $uuid = $form['uuid'] ?? null;
            $slug = $form['slug'] ?? null;
            if ($uuid === $identifier || $slug === $identifier) {
                return $form;
            }
        }

        throw new RuntimeException("Form not found in local dumps: {$identifier}");
    }

    /**
     * @param  array<string, mixed>  $form
     * @return list<string>
     */
    private function collectStepUuidsForForm(array $form): array
    {
        $steps = $form['steps'] ?? null;
        if (! is_array($steps)) {
            return [];
        }

        $uuids = [];
        foreach ($steps as $step) {
            if (is_array($step) && isset($step['uuid']) && is_string($step['uuid'])) {
                $uuids[] = $step['uuid'];
            } elseif (is_string($step)) {
                $uuids[] = $step;
            }
        }

        return $uuids;
    }

    /**
     * @param  list<array<string, mixed>>  $allSteps
     * @param  list<string>  $uuids
     * @return list<array<string, mixed>>
     */
    private function filterStepsByUuids(array $allSteps, array $uuids): array
    {
        if ($uuids === []) {
            return $allSteps;
        }

        $lookup = array_flip($uuids);
        $result = [];
        foreach ($allSteps as $step) {
            $stepUuid = $step['uuid'] ?? null;
            if (is_string($stepUuid) && isset($lookup[$stepUuid])) {
                $result[] = $step;
            }
        }

        usort($result, static function (array $a, array $b): int {
            $ai = is_int($a['index'] ?? null) ? $a['index'] : 0;
            $bi = is_int($b['index'] ?? null) ? $b['index'] : 0;

            return $ai <=> $bi;
        });

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filterByFormReference(array $items, string $formUrl, string $formUuid): array
    {
        $result = [];
        foreach ($items as $item) {
            $ref = $item['form'] ?? null;
            if (! is_string($ref)) {
                continue;
            }
            if ($ref === $formUrl || str_contains($ref, $formUuid)) {
                $result[] = $item;
            }
        }

        if ($result === []) {
            // If none matched by form reference, return all — useful when the
            // dump already contains only data for a single form.
            return $items;
        }

        return $result;
    }

    /** @param  array<string, mixed>  $form */
    private function extractString(array $form, string $key): string
    {
        $value = $form[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
