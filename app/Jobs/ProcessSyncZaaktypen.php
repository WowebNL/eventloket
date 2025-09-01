<?php

namespace App\Jobs;

use App\Models\Zaaktype;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class ProcessSyncZaaktypen implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $openzaak = new Openzaak;
        $zaaktypen = $openzaak->catalogi()->zaaktypen()->getAll();

        foreach ($zaaktypen as $zaaktype) {
            Zaaktype::create([
                'id' => $zaaktype['uuid'],
                'public_id' => $zaaktype['identificatie'],
                'name' => $zaaktype['omschrijving'],
            ]);
        }
    }
}
