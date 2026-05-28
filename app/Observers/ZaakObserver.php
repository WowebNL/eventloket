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

        // Notify all municipality users with review rights
        $reviewers = $zaak->zaaktype->municipality?->municipalityUsers()->reviewers()->get() ?? [];

        foreach ($reviewers as $user) {
            $user->notify(new NewZaak($zaak));
        }
    }
}
