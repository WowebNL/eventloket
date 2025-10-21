<?php

namespace App\Jobs;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\OpenNotificationType;
use App\Events\OpenNotification\CreateZaakNotificationReceived;
use App\Jobs\Zaak\ClearZaakCache;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class ProcessOpenNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private OpenNotification $notification) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak, GetIncommingNotificationType $typeProcessor): void
    {
        match ($typeProcessor->handle($openzaak, $this->notification)) {
            OpenNotificationType::CreateZaak => CreateZaakNotificationReceived::dispatch($this->notification),
            OpenNotificationType::UpdateZaakEigenschap => ClearZaakCache::dispatch($this->notification),
            OpenNotificationType::ZaakStatusChanged => ZaakStatusNotificationReceived::dispatch($this->notification),
            OpenNotificationType::NewZaakDocument => DocumentNotificationReceived::dispatch($this->notification, true)->delay(now()->addMinutes(1)), // delay because document needs to be linked to the zaak first
            OpenNotificationType::UpdatedZaakDocument => DocumentNotificationReceived::dispatch($this->notification, false)->delay(now()->addMinutes(1)),
            default => null,
        };
    }
}
