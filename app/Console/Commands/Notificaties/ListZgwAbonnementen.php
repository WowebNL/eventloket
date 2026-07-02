<?php

declare(strict_types=1);

namespace App\Console\Commands\Notificaties;

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

class ListZgwAbonnementen extends Command
{
    protected $signature = 'app:list-zgw-abonnementen
        {--connection= : Beperk tot één connectie (bijv. main of gemeente_5)}
        {--delete= : Abonnement-URL die verwijderd moet worden}
        {--force : Verwijderen zonder bevestiging}';

    protected $description = 'Toont per ZGW-connectie de op de Notificaties API geregistreerde abonnementen, en verwijdert er optioneel één';

    public function handle(ZgwConnectionResolver $resolver): int
    {
        $connectionNames = AbonnementRegistrar::connectionNames($resolver, $this->option('connection'));

        $rows = [];
        $failed = 0;

        foreach ($connectionNames as $connectionName) {
            $api = new NotificatiesApi($connectionName);

            try {
                $api->baseUrl();
            } catch (InvalidConfigurationException) {
                $this->warn("[{$connectionName}] Geen notificaties-URL geconfigureerd, overgeslagen.");

                continue;
            }

            try {
                foreach ($api->abonnementen() as $abonnement) {
                    $rows[] = [
                        'connection' => $connectionName,
                        'url' => is_string($abonnement['url'] ?? null) ? $abonnement['url'] : '',
                        'callbackUrl' => (string) ($abonnement['callbackUrl'] ?? ''),
                        'kanalen' => collect($abonnement['kanalen'] ?? [])
                            ->map(fn ($kanaal): string => is_array($kanaal) ? (string) ($kanaal['naam'] ?? '') : '')
                            ->filter()
                            ->implode(', '),
                    ];
                }
            } catch (Throwable $exception) {
                $this->error("[{$connectionName}] Ophalen mislukt: {$exception->getMessage()}");
                $failed++;
            }
        }

        if ($rows === []) {
            $this->info('Geen abonnementen gevonden.');
        } else {
            $this->table(
                ['Connectie', 'Abonnement-URL', 'Callback-URL', 'Kanalen'],
                array_map(fn (array $row): array => array_values($row), $rows),
            );
        }

        $deleteUrl = $this->option('delete');

        if (is_string($deleteUrl) && $deleteUrl !== '') {
            return $this->deleteAbonnement($deleteUrl, $rows);
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param  list<array{connection: string, url: string, callbackUrl: string, kanalen: string}>  $rows
     */
    private function deleteAbonnement(string $deleteUrl, array $rows): int
    {
        $connectionName = collect($rows)->firstWhere('url', $deleteUrl)['connection']
            ?? $this->option('connection');

        if (! is_string($connectionName) || $connectionName === '') {
            $this->error("Kon niet bepalen via welke connectie {$deleteUrl} verwijderd moet worden. Geef --connection mee.");

            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Abonnement {$deleteUrl} verwijderen via connectie {$connectionName}?")) {
            $this->info('Geannuleerd.');

            return Command::SUCCESS;
        }

        try {
            (new NotificatiesApi($connectionName))->deleteAbonnement($deleteUrl);
        } catch (Throwable $exception) {
            $this->error("Verwijderen mislukt: {$exception->getMessage()}");

            return Command::FAILURE;
        }

        $removed = ZgwAbonnement::where('abonnement_url', $deleteUrl)->delete();

        $this->info('Abonnement verwijderd.'.($removed > 0 ? ' Lokale registratie ook opgeschoond.' : ''));

        return Command::SUCCESS;
    }
}
