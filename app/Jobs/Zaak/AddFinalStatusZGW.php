<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ZGW\StatusType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

class AddFinalStatusZGW implements ShouldQueue
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

        $finalStatusType = new StatusType(...$connection->catalogi()->statustypen()->index(['zaaktype' => $this->finishZaakObject->zaak->openzaak->zaaktype])->collect()->where('isEindstatus', true)->first());
        $connection->zaken()->statussen()->store(array_merge($this->finishZaakObject->getPartialStatusData(), ['statustype' => $finalStatusType->url]));
    }
}
