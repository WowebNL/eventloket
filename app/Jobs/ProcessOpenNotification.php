<?php

namespace App\Jobs;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\OpenNotificationType;
use App\Jobs\Zaak\ClearZaakCache;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class ProcessOpenNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(private OpenNotification $notification) {}

    public function handle(Openzaak $openzaak, GetIncommingNotificationType $typeProcessor): void
    {
        match ($typeProcessor->handle($openzaak, $this->notification)) {
            OpenNotificationType::UpdateZaakEigenschap => ClearZaakCache::dispatch($this->notification),
            OpenNotificationType::ZaakStatusChanged => ZaakStatusNotificationReceived::dispatch($this->notification),
            OpenNotificationType::NewZaakDocument => DocumentNotificationReceived::dispatch($this->notification, true)->delay(now()->addSeconds(10)), // delay because document needs to be linked to the zaak first
            OpenNotificationType::UpdatedZaakDocument => DocumentNotificationReceived::dispatch($this->notification, false)->delay(now()->addSeconds(10)),
            OpenNotificationType::checkAndSetZaakobject => CheckAndSetZaakobject::dispatch($this->notification)->delay(now()->addMinutes(3)), // delay because OF is failing but is creating role 
            default => null,
        };
    }
}
