<?php

namespace App\Listeners\Zaak;

use App\Events\OpenNotification\CreateZaakNotificationReceived;
use App\Jobs\Zaak\AddEinddatumZGW;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateZaak;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;

class ProcessCreateZaak implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CreateZaakNotificationReceived $event): void
    {
        Bus::chain([
            new AddZaakeigenschappenZGW($event->notification->hoofdObject),
            new AddEinddatumZGW($event->notification->hoofdObject),
            new UpdateInitiatorZGW($event->notification->hoofdObject),
            // TODO add geometry to zaak
            new CreateZaak($event->notification->hoofdObject),
            // TODO send notificaties
        ])->dispatch();
    }
}
