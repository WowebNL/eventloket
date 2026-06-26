<?php

declare(strict_types=1);

namespace App\Console\Commands\Notificaties;

use App\Models\Municipality;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Notificaties\WebhookTokenIssuer;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

class RegisterZgwAbonnement extends Command
{
    protected $signature = 'app:register-zgw-abonnementen
        {--connection= : Beperk tot één connectie (bijv. main of gemeente_5)}
        {--dry-run : Simuleer zonder iets aan te maken of op te slaan}';

    protected $description = 'Registreert per ZGW-connectie een Open Notificaties abonnement op onze gedeelde webhook, met een scoped Passport-token in het auth-veld';

    /**
     * Channels the webhook subscribes to. Empty filters = every notification on
     * the channel.
     *
     * @var list<string>
     */
    private const KANALEN = ['zaken', 'besluiten', 'documenten', 'zaaktypen'];

    public function handle(ZgwConnectionResolver $resolver, WebhookTokenIssuer $issuer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN modus: er worden geen wijzigingen doorgevoerd.');
        }

        $connectionNames = $this->connectionNames($resolver);

        if ($connectionNames->isEmpty()) {
            $this->warn('Geen connecties om te registreren.');

            return Command::SUCCESS;
        }

        $failed = 0;

        foreach ($connectionNames as $connectionName) {
            try {
                $this->registerForConnection($connectionName, $issuer, $dryRun);
            } catch (Throwable $exception) {
                $this->error("[{$connectionName}] Registratie mislukt: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Klaar. Mislukt: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * The connections to register: an explicit --connection, otherwise the shared
     * "main" connection plus every municipality with its own ZGW connection.
     *
     * @return Collection<int, string>
     */
    private function connectionNames(ZgwConnectionResolver $resolver): Collection
    {
        /** @var Collection<int, string> $names */
        $names = collect();

        $explicit = $this->option('connection');

        if (is_string($explicit) && $explicit !== '') {
            return $names->push($explicit)->values();
        }

        $names->push(ZgwConnectionResolver::DEFAULT_CONNECTION);

        foreach (Municipality::has('zgwConnection')->get() as $municipality) {
            $name = $resolver->forMunicipality($municipality);

            if ($name !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
                $names->push($name);
            }
        }

        return $names->unique()->values();
    }

    private function registerForConnection(string $connectionName, WebhookTokenIssuer $issuer, bool $dryRun): void
    {
        $api = new NotificatiesApi($connectionName);

        try {
            $baseUrl = $api->baseUrl();
        } catch (InvalidConfigurationException) {
            $this->warn("[{$connectionName}] Geen notificaties-URL geconfigureerd, overgeslagen.");

            return;
        }

        $callbackUrl = URL::route('api.open-notifications.listen');

        $this->info("[{$connectionName}] Abonnement registreren op {$baseUrl}abonnement (callback {$callbackUrl})");

        if ($dryRun) {
            $this->line("  [DRY-RUN] Zou een scoped token aanvragen en een abonnement aanmaken/bijwerken op kanalen: ".implode(', ', self::KANALEN));

            return;
        }

        $token = $issuer->issue();

        $existing = collect($api->abonnementen())
            ->first(fn (array $abonnement) => ($abonnement['callbackUrl'] ?? null) === $callbackUrl);

        if (is_array($existing) && isset($existing['url'])) {
            $abonnementUrl = (string) $existing['url'];
            $api->patchAbonnement($abonnementUrl, ['auth' => 'Bearer '.$token->token]);
            $this->line('  Bestaand abonnement bijgewerkt met een nieuw token.');
        } else {
            $created = $api->createAbonnement([
                'callbackUrl' => $callbackUrl,
                'auth' => 'Bearer '.$token->token,
                'kanalen' => array_map(fn (string $naam) => ['naam' => $naam, 'filters' => (object) []], self::KANALEN),
            ]);
            $abonnementUrl = isset($created['url']) ? (string) $created['url'] : null;
            $this->line('  Nieuw abonnement aangemaakt.');
        }

        ZgwAbonnement::updateOrCreate(
            ['connection' => $connectionName],
            [
                'municipality_id' => ZgwAbonnement::municipalityIdFromConnection($connectionName),
                'notificaties_base_url' => $baseUrl,
                'callback_url' => $callbackUrl,
                'abonnement_url' => $abonnementUrl,
                'token_id' => $token->tokenId,
                'expires_at' => $token->expiresAt,
                'last_renewed_at' => now(),
            ],
        );
    }
}
