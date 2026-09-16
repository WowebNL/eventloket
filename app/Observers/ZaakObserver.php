<?php

namespace App\Observers;

use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Models\Zaak;
use App\Notifications\NewZaak;
use App\Services\Zgw\ZgwConnectionResolver;

class ZaakObserver
{
    /**
     * Record which ZGW connection issued this zaak's number.
     *
     * `public_id` is only unique within the instance that assigned it, so the
     * unique index on `zaken` is scoped to this column. Deriving it here rather
     * than at each call site keeps a single source for the answer: every path
     * that creates a zaak (a form submission, a doorkomst deelzaak, a recovery
     * run) already routes its ZGW calls through the same resolver, so the column
     * cannot disagree with the connection the zaak was actually created on.
     *
     * The resolver is a container singleton that memoises per municipality, so
     * asking it again here returns the answer the creating call used, including
     * any fallback to the shared connection that was applied along the way.
     *
     * An explicitly supplied value wins. The import path needs that: it saves
     * without model events, so it fills the column itself.
     */
    public function creating(Zaak $zaak): void
    {
        if (filled($zaak->zgw_connection)) {
            return;
        }

        $zaak->zgw_connection = app(ZgwConnectionResolver::class)->for($zaak);
    }

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
