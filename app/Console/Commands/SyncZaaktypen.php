<?php

namespace App\Console\Commands;

use App\Models\Zaaktype;
use Illuminate\Console\Command;
use Woweb\Openzaak\Openzaak;

class SyncZaaktypen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-zaaktypen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs zaaktypen from the connected Open Zaak instance';

    /**
     * Execute the console command.
     */
    public function handle(Openzaak $openzaak)
    {
        $this->info('Syncing zaaktypen...');

        // Fetch zaaktypen from Open Zaak
        $zaaktypen = $openzaak->catalogi()->zaaktypen()->getAll();
        $updatedIds = [];

        foreach ($zaaktypen as $data) {
            $zaaktype = Zaaktype::updateOrCreate(
                ['zgw_zaaktype_url' => $data['url']],
                [
                    'name' => $data['omschrijving'],
                    'is_active' => true,
                ]
            );
            $updatedIds[] = $zaaktype->id;
        }

        // deactivate all zaaktypen that were not in the openzaak response
        Zaaktype::whereNotIn('id', $updatedIds)->update(['is_active' => false]);

        $this->info('Zaaktypen synced successfully.');

        return Command::SUCCESS;
    }
}
