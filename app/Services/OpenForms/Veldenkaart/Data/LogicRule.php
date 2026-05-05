<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class LogicRule implements Arrayable
{
    /**
     * @param  array<string, mixed>|list<mixed>  $jsonLogicTrigger
     * @param  list<LogicAction>  $actions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly int $order,
        public readonly string $description,
        public readonly bool $isAdvanced,
        public readonly ?string $triggerFromStepUuid,
        public readonly ?string $triggerFromStepName,
        public readonly array $jsonLogicTrigger,
        public readonly array $actions,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'order' => $this->order,
            'description' => $this->description,
            'is_advanced' => $this->isAdvanced,
            'trigger_from_step_uuid' => $this->triggerFromStepUuid,
            'trigger_from_step_name' => $this->triggerFromStepName,
            'json_logic_trigger' => $this->jsonLogicTrigger,
            'actions' => array_map(static fn (LogicAction $a): array => $a->toArray(), $this->actions),
        ];
    }
}
