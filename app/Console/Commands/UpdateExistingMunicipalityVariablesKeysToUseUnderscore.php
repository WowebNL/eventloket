<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use Illuminate\Console\Command;

class UpdateExistingMunicipalityVariablesKeysToUseUnderscore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-existing-municipality-variables-keys-to-use-underscore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a one time command to update existing municipality variable keys to use underscores instead of dashes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $variables = MunicipalityVariable::where('key', 'LIKE', '%-%')->get();
        foreach ($variables as $variable) {
            $newKey = str_replace('-', '_', $variable->key);

            // Check if the new key already exists for the same municipality
            $exists = MunicipalityVariable::where('municipality_id', $variable->municipality_id)
                ->where('key', $newKey)
                ->exists();

            if ($exists) {
                $this->warn("Skipping variable ID {$variable->id} for municipality ID {$variable->municipality_id} because the new key '{$newKey}' already exists.");

                continue;
            }

            $this->info("Updating variable ID {$variable->id} for municipality ID {$variable->municipality_id} from key '{$variable->key}' to '{$newKey}'.");

            $variable->key = $newKey;
            $variable->save();
        }
    }
}
