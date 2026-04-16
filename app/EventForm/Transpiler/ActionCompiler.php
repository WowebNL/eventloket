<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

use RuntimeException;

/**
 * Compileert één OF-action naar een PHP-statement dat op een `FormState $s`
 * opereert. Gebruikt in gegenereerde Rule-klassen als body van `apply()`.
 *
 * Ondersteunt alle 6 OF-action-types uit ons formulier:
 *  - property            → setFieldHidden($key, $state)
 *  - variable            → setVariable($key, <value|jsonlogic>)
 *  - step-applicable     → setStepApplicable($uuid, true)
 *  - step-not-applicable → setStepApplicable($uuid, false)
 *  - set-registration-backend → setSystem('registration_backend', $value)
 *  - fetch-from-service  → no-op body; de Filament-page dispatcht de HTTP-call
 *                          via ServiceFetcher op basis van rule-metadata.
 */
class ActionCompiler
{
    public function __construct(
        private readonly JsonLogicCompiler $logic,
    ) {}

    /**
     * @param  array<string, mixed>  $action  Volledige action-entry uit
     *                                        formLogic.json:
     *                                        ['component' => ..., 'variable' => ...,
     *                                        'form_step_uuid' => ..., 'action' => [...]].
     * @return string PHP statement eindigend op `;` (of lege string voor no-op).
     */
    public function compile(array $action): string
    {
        $payload = $action['action'] ?? [];
        if (! is_array($payload)) {
            throw new RuntimeException('Action entry missing `action` payload');
        }

        $type = (string) ($payload['type'] ?? '');

        return match ($type) {
            'property' => $this->compileProperty($action, $payload),
            'variable' => $this->compileVariable($action, $payload),
            'step-applicable' => $this->compileStepApplicable($action, true),
            'step-not-applicable' => $this->compileStepApplicable($action, false),
            'set-registration-backend' => $this->compileSetRegistrationBackend($payload),
            'fetch-from-service' => '',
            default => throw new RuntimeException("Unsupported action type: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $payload
     */
    private function compileProperty(array $action, array $payload): string
    {
        $component = (string) ($action['component'] ?? '');
        if ($component === '') {
            throw new RuntimeException('property-action must target a component');
        }

        /** @var array<string, mixed> $property */
        $property = is_array($payload['property'] ?? null) ? $payload['property'] : [];
        $propName = (string) ($property['value'] ?? '');

        if ($propName !== 'hidden') {
            // Alleen `hidden` komt voor in ons formulier (98/98). Andere
            // properties zouden we pas ondersteunen als ze echt opduiken.
            throw new RuntimeException("property `{$propName}` is not supported");
        }

        $state = (bool) ($payload['state'] ?? false);

        return sprintf(
            '$s->setFieldHidden(%s, %s);',
            var_export($component, true),
            $state ? 'true' : 'false',
        );
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $payload
     */
    private function compileVariable(array $action, array $payload): string
    {
        $variable = (string) ($action['variable'] ?? '');
        if ($variable === '') {
            throw new RuntimeException('variable-action must name a variable');
        }

        $valuePhp = $this->logic->compile($payload['value'] ?? null);

        return sprintf(
            '$s->setVariable(%s, %s);',
            var_export($variable, true),
            $valuePhp,
        );
    }

    /** @param  array<string, mixed>  $action */
    private function compileStepApplicable(array $action, bool $applicable): string
    {
        $stepUuid = (string) ($action['form_step_uuid'] ?? '');
        if ($stepUuid === '') {
            throw new RuntimeException('step-(not-)applicable action must target a step uuid');
        }

        return sprintf(
            '$s->setStepApplicable(%s, %s);',
            var_export($stepUuid, true),
            $applicable ? 'true' : 'false',
        );
    }

    /** @param  array<string, mixed>  $payload */
    private function compileSetRegistrationBackend(array $payload): string
    {
        $value = (string) ($payload['value'] ?? '');

        return sprintf(
            '$s->setSystem(\'registration_backend\', %s);',
            var_export($value, true),
        );
    }
}
