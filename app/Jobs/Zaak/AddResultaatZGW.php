<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class AddResultaatZGW implements ShouldQueue
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
        $openzaak->zaken()->resultaten()->store($this->finishZaakObject->getResultaatData());
    }
}
