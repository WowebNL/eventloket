<?php

declare(strict_types=1);

namespace App\Console\Commands\Notificaties;

use App\Exceptions\NotificatiesHealthException;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\WebhookIngressHealthCheck;
use App\Services\Notificaties\WebhookIngressStatus;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks, on a schedule, whether incoming notifications can still reach us, and
 * reports every answer that is not "yes" as a defect.
 *
 * The checks it runs already existed. What did not exist was anything asking
 * them: the abonnement health check had a single caller, a UI component bound to
 * a per-municipality connection row, which the shared connection does not have.
 * A check nothing invokes reports nothing, and a pipeline that has quietly
 * stopped delivering looks exactly like one with nothing to deliver.
 *
 * Findings leave through the exception handler so they surface wherever other
 * defects do, and the command exits non-zero so a failing scheduled run is a
 * second, independent place the fault shows up.
 *
 * Two outcomes are deliberately silent: a healthy abonnement, and a token inside
 * its renewal window, because that window is exactly when the renewal job acts.
 * Reporting a condition the system resolves by itself trains everyone to ignore
 * the report, which is the same failure in a new shape.
 */
class CheckZgwNotificatiesHealth extends Command
{
    protected $signature = 'app:check-zgw-notificaties
        {--connection= : Beperk tot één connectie (bijv. main of gemeente_5)}';

    protected $description = 'Controleert per ZGW-connectie of de Open Notificaties-koppeling nog werkt en meldt elk defect, zodat een weggevallen notificatiestroom niet stil blijft';

    public function handle(
        ZgwConnectionResolver $resolver,
        AbonnementHealthCheck $abonnementen,
        WebhookIngressHealthCheck $ingress,
    ): int {
        $connectionOption = $this->option('connection');
        $problems = 0;

        $connectionNames = AbonnementRegistrar::connectionNames(
            $resolver,
            is_string($connectionOption) ? $connectionOption : null,
        );

        foreach ($connectionNames as $connectionName) {
            $problems += $this->checkConnection($abonnementen, $connectionName);
        }

        $problems += $this->checkIngress($ingress);

        if ($problems === 0) {
            $this->info('Open Notificaties-koppeling in orde.');

            return self::SUCCESS;
        }

        $this->error("Open Notificaties-koppeling: {$problems} probleem/problemen gemeld.");

        return self::FAILURE;
    }

    /**
     * @return int the number of problems found for this connection
     */
    private function checkConnection(AbonnementHealthCheck $abonnementen, string $connectionName): int
    {
        try {
            $result = $abonnementen->run($connectionName);
        } catch (Throwable $exception) {
            // Failing to complete the check is itself something nobody would
            // otherwise notice, so it is reported instead of swallowed.
            $this->reportProblem(
                "[{$connectionName}] Controle van het Open Notificaties-abonnement mislukt: {$exception->getMessage()}",
                ['connection' => $connectionName],
            );

            return 1;
        }

        if ($this->needsNoAttention($result->status)) {
            $this->line("[{$connectionName}] ok");

            return 0;
        }

        $this->reportProblem(
            "[{$connectionName}] Open Notificaties-abonnement niet in orde: {$result->status->translationKey()}",
            array_filter([
                'connection' => $connectionName,
                'status' => $result->status->translationKey(),
                'missing_kanalen' => $result->missingKanalen === [] ? null : implode(',', $result->missingKanalen),
                'expires_at' => $result->expiresAt?->toIso8601String(),
            ], fn (?string $value): bool => $value !== null),
        );

        return 1;
    }

    /**
     * @return int the number of problems found on the shared webhook ingress
     */
    private function checkIngress(WebhookIngressHealthCheck $ingress): int
    {
        $status = $ingress->run();

        $message = match ($status) {
            WebhookIngressStatus::Rejecting => 'Open Notificaties-webhook wijst inkomende meldingen aanhoudend af.',
            WebhookIngressStatus::Silent => 'Open Notificaties-webhook heeft al langere tijd geen melding geaccepteerd terwijl er een abonnement geregistreerd staat.',
            WebhookIngressStatus::Healthy => null,
        };

        if ($message === null) {
            $this->line('[webhook] ok');

            return 0;
        }

        $this->reportProblem($message, ['ingress' => strtolower($status->name)]);

        return 1;
    }

    /**
     * Healthy needs nothing, and a token inside its renewal window is what the
     * renewal job exists for, so neither counts as a defect.
     */
    private function needsNoAttention(AbonnementCheckStatus $status): bool
    {
        return $status === AbonnementCheckStatus::Healthy
            || $status === AbonnementCheckStatus::TokenExpiringSoon;
    }

    /**
     * Surface one finding: through the exception handler so it reaches the error
     * tracker, in the log so there is a local trail, and on the console so an
     * operator running this by hand sees it too.
     *
     * The context holds connection names, statuses and timestamps. No request
     * data passes through here.
     *
     * @param  array<string, string>  $context
     */
    private function reportProblem(string $message, array $context): void
    {
        Log::error($message, $context);

        report(new NotificatiesHealthException($message));

        $this->warn($message);
    }
}
