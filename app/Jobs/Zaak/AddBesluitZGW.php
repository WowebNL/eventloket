<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ZGW\Besluit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

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
    public function handle(Openzaak $openzaak): void
    {
        $besluit = new Besluit(...$openzaak->besluiten()->besluiten()->store($this->finishZaakObject->getBesluitData())->toArray());

        // attach documenten to besluit if any
        foreach ($this->finishZaakObject->getBesluitDocumenten() as $documentUrl) {
            $openzaak->besluiten()->besluitinformatieobjecten()->store([
                'besluit' => $besluit->url,
                'informatieobject' => $documentUrl,
            ]);
        }
    }
}
