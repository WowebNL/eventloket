<?php

namespace App\Console\Commands;

use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Zaak;
use Illuminate\Console\Command;

class CreateDoorkomstZakenForZaak extends Command
{
    protected $signature = 'zaak:create-doorkomst-zaken {public_id : The public ID of the zaak}';

    protected $description = 'Dispatch the CreateDoorkomstZaken job for a specific zaak by public_id.';

    public function handle(): int
    {
        $publicId = $this->argument('public_id');

        $zaak = Zaak::where('public_id', $publicId)->first();

        if (! $zaak) {
            $this->error("No zaak found with public_id: {$publicId}");

            return self::FAILURE;
        }

        if (! $zaak->zgw_zaak_url) {
            $this->error("Zaak with public_id {$publicId} has no zgw_zaak_url.");

            return self::FAILURE;
        }

        CreateDoorkomstZaken::dispatch($zaak->zgw_zaak_url);

        $this->info("Dispatched CreateDoorkomstZaken job for zaak {$publicId} ({$zaak->zgw_zaak_url}).");

        return self::SUCCESS;
    }
}
