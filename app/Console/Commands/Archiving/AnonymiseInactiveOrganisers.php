<?php

namespace App\Console\Commands\Archiving;

use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AVG (storage limitation): anonymises organiser accounts that no longer have
 * any zaken anywhere and have been inactive for a configurable period. This is
 * deliberately separate from the destruction of a single list, because an
 * organiser can still have zaken at other municipalities.
 */
class AnonymiseInactiveOrganisers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archiving:anonymise-inactive-organisers {--dry-run : Only report which accounts would be anonymised}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Anonymise organiser accounts without zaken that have been inactive for the configured period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = now()->subMonths((int) config('archiving.organiser_inactivity_months'));

        $organisers = OrganiserUser::query()
            ->whereNull('anonymised_at')
            ->where('updated_at', '<', $threshold)
            ->get()
            ->filter(fn (OrganiserUser $organiser) => ! Zaak::withTrashed()
                ->where('organiser_user_id', $organiser->id)
                ->exists());

        foreach ($organisers as $organiser) {
            if ($this->option('dry-run')) {
                $this->line("Would anonymise organiser [{$organiser->id}] {$organiser->email}");

                continue;
            }

            $this->anonymise($organiser);

            $this->line("Anonymised organiser [{$organiser->id}]");
        }

        $this->info(($this->option('dry-run') ? 'Would anonymise ' : 'Anonymised ').$organisers->count().' organiser account(s)');

        return self::SUCCESS;
    }

    private function anonymise(OrganiserUser $organiser): void
    {
        $organiser->forceFill([
            'first_name' => null,
            'last_name' => null,
            'name' => 'Geanonimiseerde gebruiker',
            'email' => 'anonymised-'.Str::uuid().'@anonymised.invalid',
            'phone' => null,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'openzaak_jwt' => null,
            'openzaak_jwt_valid_till' => null,
            'anonymised_at' => now(),
        ])->save();
    }
}
