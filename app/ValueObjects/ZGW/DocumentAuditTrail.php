<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

class DocumentAuditTrail implements Arrayable
{
    public readonly ?array $otherParams;

    public string $friendlyAction;

    public function __construct(
        public readonly string $uuid,
        public readonly string $bron,
        public readonly string $applicatieWeergave,
        public readonly string $gebruikersWeergave,
        public readonly string $actieWeergave,
        public readonly string $aanmaakdatum,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        $this->setFriendlyAction();
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'bron' => $this->bron,
            'applicatieWeergave' => $this->applicatieWeergave,
            'gebruikersWeergave' => $this->gebruikersWeergave,
            'actieWeergave' => $this->actieWeergave,
            'friendlyAction' => $this->friendlyAction,
            'aanmaakdatum' => $this->aanmaakdatum,
            'otherParams' => $this->otherParams,
        ];
    }

    private function setFriendlyAction(): void
    {
        match ($this->actieWeergave) {
            'Object aangemaakt' => $this->friendlyAction = __('Document aangemaakt'),
            'Object deels bijgewerkt' => $this->friendlyAction = __('Document gewijzigd'),
            default => $this->friendlyAction = $this->actieWeergave,
        };
    }
}
