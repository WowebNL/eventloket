<?php

namespace App\Observers;

use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Models\Zaak;

class ZaakObserver
{
    /**
     * Handle the Zaak "created" event.
     */
    public function created(Zaak $zaak): void
    {
        CreateConceptAdviceQuestions::dispatch($zaak);
    }
}
