<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

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
    public function handle(): void
    {
        Zgw::connection($this->finishZaakObject->zaak->zgwConnectionName())
            ->zaken()->resultaten()->store($this->finishZaakObject->getResultaatData());
    }
}
