<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Loaders;

use App\Services\OpenForms\Veldenkaart\Data\RawFormData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiLoader implements LoaderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    public function load(string $formIdentifier): RawFormData
    {
        $form = $this->fetchForm($formIdentifier);
        $formUuid = $this->requireString($form, 'uuid');

        $stepUuids = $this->collectStepUuids($form);
        $formSteps = [];
        foreach ($stepUuids as $stepUuid) {
            $formSteps[] = $this->fetchJson("/api/v2/forms/{$formUuid}/steps/{$stepUuid}");
        }

        $logicRules = $this->fetchJsonList("/api/v2/forms/{$formUuid}/logic-rules");
        $variables = $this->fetchJsonList("/api/v2/forms/{$formUuid}/variables");

        return new RawFormData(
            form: $form,
            formSteps: $formSteps,
            logicRules: $logicRules,
            variables: $variables,
            meta: [
                'source' => $this->sourceLabel(),
            ],
        );
    }

    public function sourceLabel(): string
    {
        return 'api:'.$this->baseUrl;
    }

    /** @return array<string, mixed> */
    private function fetchForm(string $identifier): array
    {
        // Try direct UUID fetch first.
        if (preg_match('/^[0-9a-f-]{36}$/i', $identifier) === 1) {
            return $this->fetchJson("/api/v2/forms/{$identifier}");
        }

        // Fall back to listing and matching on slug.
        $forms = $this->fetchJsonList('/api/v2/forms');
        foreach ($forms as $form) {
            if (($form['slug'] ?? null) === $identifier) {
                $uuid = $form['uuid'] ?? null;
                if (is_string($uuid)) {
                    return $this->fetchJson("/api/v2/forms/{$uuid}");
                }
            }
        }

        throw new RuntimeException("Form not found via API: {$identifier}");
    }

    /**
     * @param  array<string, mixed>  $form
     * @return list<string>
     */
    private function collectStepUuids(array $form): array
    {
        $steps = $form['steps'] ?? null;
        $uuids = [];

        if (is_array($steps)) {
            foreach ($steps as $step) {
                if (is_array($step) && isset($step['uuid']) && is_string($step['uuid'])) {
                    $uuids[] = $step['uuid'];
                }
            }
        }

        return $uuids;
    }

    /** @return array<string, mixed> */
    private function fetchJson(string $path): array
    {
        $response = $this->client()->get($path);
        if ($response->failed()) {
            throw new RuntimeException(
                "OF API call failed: {$path} (status {$response->status()})"
            );
        }

        /** @var mixed $json */
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException("OF API returned non-array for {$path}");
        }

        /** @var array<string, mixed> $json */
        return $json;
    }

    /** @return list<array<string, mixed>> */
    private function fetchJsonList(string $path): array
    {
        $response = $this->client()->get($path);
        if ($response->failed()) {
            throw new RuntimeException(
                "OF API call failed: {$path} (status {$response->status()})"
            );
        }

        /** @var mixed $json */
        $json = $response->json();

        $items = [];
        if (is_array($json)) {
            // Some OF endpoints paginate; others return a bare array.
            if (array_is_list($json)) {
                $items = $json;
            } elseif (isset($json['results']) && is_array($json['results'])) {
                $items = $json['results'];
            }
        }

        /** @var list<array<string, mixed>> $result */
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $result[] = $item;
            }
        }

        return $result;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                'Authorization' => 'Token '.$this->token,
                'Accept' => 'application/json',
            ])
            ->acceptJson()
            ->timeout(30);
    }

    /** @param  array<string, mixed>  $form */
    private function requireString(array $form, string $key): string
    {
        $value = $form[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("OF API response missing string field: {$key}");
        }

        return $value;
    }
}
