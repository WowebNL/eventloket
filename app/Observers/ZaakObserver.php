<?php

namespace App\Observers;

use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Models\Zaak;
use App\Notifications\NewZaak;

class ZaakObserver
{
    /**
     * Handle the Zaak "created" event.
     */
    public function created(Zaak $zaak): void
    {
        CreateConceptAdviceQuestions::dispatch($zaak);

        $recipients = $zaak->getMunicipalityHandlers();

        foreach ($recipients as $user) {
            $user->notify(new NewZaak($zaak));
        }
    }
}
