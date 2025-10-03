<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ZGW\StatusType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

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
    public function handle(Openzaak $openzaak): void
    {
        $finalStatusType = new StatusType(...$openzaak->catalogi()->statustypen()->getAll(['zaaktype' => $this->finishZaakObject->zaak->openzaak->zaaktype])->where('isEindstatus', true)->first());
        $openzaak->zaken()->statussen()->store(array_merge($this->finishZaakObject->getPartialStatusData(), ['statustype' => $finalStatusType->url]));
    }
}
