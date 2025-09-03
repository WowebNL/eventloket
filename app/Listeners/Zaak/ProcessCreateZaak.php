<?php

namespace App\Listeners\Zaak;

use App\Events\OpenNotification\CreateZaakNotificationReceived;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateZaak;
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
            // TODO set uiterlijke eindatum afdoening en eindatum gepland
            // TODO add geometry to zaak
            new CreateZaak($event->notification->hoofdObject),
            // TODO send notificaties
        ])->dispatch();
    }
}
