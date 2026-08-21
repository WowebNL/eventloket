<?php

namespace App\Console\Commands\Archiving;

use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        $months = (int) config('archiving.organiser_inactivity_months');
        $threshold = now()->subMonths($months);

        $this->warnAboutActivityLogRetention($months);

        // An account counts as dormant when it has not logged in since the
        // threshold and was itself created before it. The created_at condition
        // is what lets accounts that never logged in age out too.
        $organisers = OrganiserUser::query()
            ->whereNull('anonymised_at')
            ->where('created_at', '<', $threshold)
            ->whereNotIn('id', $this->userIdsWithLoginSince($threshold))
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

    /**
     * Logins are recorded in the activity log by the LogLogin listener, which
     * is the only record of user activity we keep.
     *
     * The ids are read in a separate query rather than a correlated subquery
     * on purpose: activity_log.causer_id is a string column on PostgreSQL and
     * an integer on MySQL, so comparing it against users.id in SQL is not
     * portable across both drivers.
     *
     * @return array<int>
     */
    private function userIdsWithLoginSince(CarbonInterface $threshold): array
    {
        return DB::table('activity_log')
            ->where('log_name', 'auth')
            ->where('event', 'login')
            ->where('causer_type', User::class)
            ->where('created_at', '>=', $threshold)
            ->distinct()
            ->pluck('causer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Activity log entries can be pruned. If they are removed sooner than the
     * inactivity window, a login can disappear before the account has aged
     * out, which would make an active organiser look dormant.
     */
    private function warnAboutActivityLogRetention(int $inactivityMonths): void
    {
        $retentionDays = (int) config('activitylog.delete_records_older_than_days');
        $inactivityDays = $inactivityMonths * 30;

        if ($retentionDays > 0 && $retentionDays < $inactivityDays) {
            $this->warn(
                "Activity log retention is {$retentionDays} days but inactivity is measured over ~{$inactivityDays} days. ".
                'Logins older than the retention period cannot be seen, so accounts may look dormant while they are not. '.
                'Raise activitylog.delete_records_older_than_days above the inactivity window.'
            );
        }
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
