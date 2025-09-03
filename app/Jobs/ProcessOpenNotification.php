<?php

namespace App\Jobs;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\OpenNotificationType;
use App\Events\OpenNotification\CreateZaakNotificationReceived;
use App\Exceptions\UnknownOpenNotificationException;
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
            default => throw new UnknownOpenNotificationException('Unknown notification type'),
        };
    }
}
