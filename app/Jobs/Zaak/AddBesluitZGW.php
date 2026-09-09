<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ZGW\Besluit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

class AddBesluitZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public FinishZaakObject $finishZaakObject) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $connection = Zgw::connection($this->finishZaakObject->zaak->zgwConnectionName());

        $besluit = new Besluit(...$connection->besluiten()->besluiten()->store($this->finishZaakObject->getBesluitData()));

        // attach documenten to besluit if any
        foreach ($this->finishZaakObject->getBesluitDocumenten() as $documentUrl) {
            $connection->besluiten()->besluitinformatieobjecten()->store([
                'besluit' => $besluit->url,
                'informatieobject' => $documentUrl,
            ]);
        }
    }
}
