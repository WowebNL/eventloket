<?php

declare(strict_types=1);

namespace App\Console\Commands\Notificaties;

use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\AbonnementRegistrationOutcome;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Throwable;

class RegisterZgwAbonnement extends Command
{
    protected $signature = 'app:register-zgw-abonnementen
        {--connection= : Beperk tot één connectie (bijv. main of gemeente_5)}
        {--dry-run : Simuleer zonder iets aan te maken of op te slaan}';

    protected $description = 'Registreert per ZGW-connectie een Open Notificaties abonnement op onze gedeelde webhook, met een scoped Passport-token in het auth-veld';

    public function handle(ZgwConnectionResolver $resolver, AbonnementRegistrar $registrar): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN modus: er worden geen wijzigingen doorgevoerd.');
        }

        $connectionNames = AbonnementRegistrar::connectionNames($resolver, $this->option('connection'));

        if ($connectionNames === []) {
            $this->warn('Geen connecties om te registreren.');

            return Command::SUCCESS;
        }

        $failed = 0;

        foreach ($connectionNames as $connectionName) {
            try {
                $outcome = $registrar->register($connectionName, $dryRun);
                $this->line("[{$connectionName}] ".$this->describe($outcome));
            } catch (Throwable $exception) {
                $this->error("[{$connectionName}] Registratie mislukt: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Klaar. Mislukt: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function describe(AbonnementRegistrationOutcome $outcome): string
    {
        return match ($outcome) {
            AbonnementRegistrationOutcome::Created => 'Nieuw abonnement aangemaakt.',
            AbonnementRegistrationOutcome::Updated => 'Bestaand abonnement bijgewerkt met een nieuw token.',
            AbonnementRegistrationOutcome::SkippedNoNotificatiesUrl => 'Geen notificaties-URL geconfigureerd, overgeslagen.',
            AbonnementRegistrationOutcome::DryRun => '[DRY-RUN] Zou een scoped token aanvragen en een abonnement aanmaken/bijwerken.',
        };
    }
}
