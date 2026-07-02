<?php

namespace App\Jobs;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\OpenNotificationType;
use App\Jobs\Zaak\ClearZaakCache;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOpenNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(private OpenNotification $notification) {}

    public function handle(GetIncommingNotificationType $typeProcessor): void
    {
        match ($typeProcessor->handle($this->notification)) {
            OpenNotificationType::UpdateZaakEigenschap => ClearZaakCache::dispatch($this->notification),
            OpenNotificationType::ZaakStatusChanged => ZaakStatusNotificationReceived::dispatch($this->notification),
            OpenNotificationType::NewZaakDocument => DocumentNotificationReceived::dispatch($this->notification, true)->delay(now()->addSeconds(10)), // delay because document needs to be linked to the zaak first
            OpenNotificationType::UpdatedZaakDocument => DocumentNotificationReceived::dispatch($this->notification, false)->delay(now()->addSeconds(10)),
            // Delay so the burst of notifications a zaaktype publish fires
            // (zaaktype update + child-resource creates, all sharing the same
            // hoofdObject) collapses into the one unique job.
            OpenNotificationType::ZaaktypeChanged => ZaaktypeNotificationReceived::dispatch($this->notification)->delay(now()->addSeconds(30)),
            default => null,
        };
    }
}
