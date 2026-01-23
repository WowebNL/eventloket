<?php

namespace App\Console\Commands;

use App\Jobs\Zaak\ClearZaakCache;
use App\Jobs\Zaak\CreateZaak;
use App\Models\Zaak;
use App\ValueObjects\OpenNotification;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\InteractsWithIO;

class UpdateZaakReferenceDataProperty extends Command
{
    use InteractsWithIO;

    protected $signature = 'zaak:update-reference-property {--property= : The property name to check}';

    protected $description = 'Queue CreateZaak jobs for zaak records that are missing a specific property in reference_data.';

    private ?string $propertyName = null;

    public function handle(): void
    {
        $this->info('🔄 Zaak Reference Data Property Checker');
        $this->line('');

        // Step 1: Get property name
        $this->propertyName = $this->option('property');

        if (empty($this->propertyName)) {
            $this->propertyName = $this->ask('Enter the property name to check:');
        }

        if (empty($this->propertyName)) {
            $this->error('Property name is required.');

            return;
        }

        // Step 2: Count records that need updating
        $totalCount = Zaak::count();
        $missingCount = Zaak::whereJsonDoesntContainKey("reference_data->{$this->propertyName}")->count();

        if ($missingCount === 0) {
            $this->info("✅ All $totalCount records already have the '{$this->propertyName}' property.");

            return;
        }

        $this->info("Found $missingCount out of $totalCount records missing the '{$this->propertyName}' property.");
        $this->line('');

        // Step 3: Confirm and dispatch jobs
        if (! $this->confirm("Do you want to queue $missingCount CreateZaak jobs to update these records?")) {
            $this->info('Update cancelled.');

            return;
        }

        $this->dispatchJobs($missingCount);
    }

    /**
     * Dispatch CreateZaak jobs for records missing the property
     */
    private function dispatchJobs(int $totalCount): void
    {
        $dispatched = 0;
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        Zaak::whereJsonDoesntContainKey("reference_data->{$this->propertyName}")
            ->lazy()
            ->each(function (Zaak $zaak) use (&$dispatched, $bar) {
                if ($zaak->zgw_zaak_url) {
                    dispatch(new ClearZaakCache(
                        new OpenNotification(
                            hoofdObject: $zaak->zgw_zaak_url,
                            actie: '',
                            kanaal: '',
                            resource: '',
                            resourceUrl: '',
                            aanmaakdatum: '',
                        )
                    ));
                }

                $dispatched++;
                $bar->advance();
            });

        $bar->finish();
        $this->line('');
        $this->line('');

        $this->info("✅ Dispatched $dispatched resync reference_data jobs to the queue!");
        $this->info('Jobs will be processed by your queue worker.');
        $this->line('');
        $this->info('Monitor jobs with: php artisan queue:monitor');
    }
}
