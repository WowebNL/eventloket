<?php

namespace App\Console\Commands\Zaaktypen;

use App\Models\Zaaktype;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;
use Woweb\Openzaak\Connection\OpenzaakConnection;
use Woweb\Openzaak\Openzaak;

class SyncZaaktypeEigenschappen extends Command
{
    protected $signature = 'app:sync-zaaktype-eigenschappen';

    protected $description = 'Voegt ontbrekende eigenschappen toe aan ieder gesynct zaaktype in Open Zaak';

    /**
     * @var array<string, array{definitie: string, specificatie: array<string, mixed>}>
     */
    private const REQUIRED_EIGENSCHAPPEN = [
        'intern_zaaknummer' => [
            'definitie' => 'Intern zaaknummer',
            'specificatie' => [
                'groep' => 'Tekst 1x',
                'formaat' => 'tekst',
                'lengte' => '255',
                'kardinaliteit' => '1',
                'waardenverzameling' => [],
            ],
        ],
    ];

    public function handle(Openzaak $openzaak): int
    {
        $this->info('Eigenschappen aanvullen voor gesynchroniseerde zaaktypen...');

        $zaaktypen = Zaaktype::where('is_active', true)
            ->whereNotNull('zgw_zaaktype_url')
            ->get();

        if ($zaaktypen->isEmpty()) {
            $this->warn('Geen actieve zaaktypen gevonden. Voer eerst app:sync-zaaktypen uit.');

            return Command::FAILURE;
        }

        $connection = new OpenzaakConnection;
        $headers = $connection->getHeaders();
        $baseUrl = rtrim((string) config('openzaak.url'), '/').'/catalogi/api/v1/';

        $updated = 0;
        $alreadyCorrect = 0;
        $failed = 0;

        foreach ($zaaktypen as $zaaktype) {
            $catalogiEigenschappen = $openzaak->catalogi()->eigenschappen()
                ->getAll(['zaaktype' => $zaaktype->zgw_zaaktype_url])
                ->map(fn ($item) => new CatalogiEigenschap(...$item));

            $missing = collect(self::REQUIRED_EIGENSCHAPPEN)
                ->keys()
                ->reject(fn (string $naam) => $catalogiEigenschappen->contains(fn (CatalogiEigenschap $eigenschap) => $eigenschap->naam === $naam))
                ->values();

            if ($missing->isEmpty()) {
                $this->line("  <comment>Al correct</comment>: {$zaaktype->name}");
                $alreadyCorrect++;

                continue;
            }

            $this->line("  Aanvullen: {$zaaktype->name} (".$missing->implode(', ').')');

            $uuid = basename((string) $zaaktype->zgw_zaaktype_url);

            $currentResponse = Http::withHeaders($headers)->get($zaaktype->zgw_zaaktype_url);

            if (! $currentResponse->successful()) {
                $this->warn("    Ophalen mislukt: HTTP {$currentResponse->status()}");
                $failed++;

                continue;
            }

            $isConcept = (bool) ($currentResponse->json('concept') ?? false);

            // Eigenschappen kunnen alleen aan een concept-zaaktype worden toegevoegd.
            if (! $isConcept) {
                $conceptResponse = Http::withHeaders($headers)->patch(
                    $baseUrl."zaaktypen/{$uuid}",
                    ['concept' => true]
                );

                if (! $conceptResponse->successful()) {
                    $this->warn("    Terugzetten naar concept mislukt (HTTP {$conceptResponse->status()}).");
                    $failed++;

                    continue;
                }
            }

            $eigenschapFailed = false;

            foreach ($missing as $naam) {
                $definition = self::REQUIRED_EIGENSCHAPPEN[$naam];

                try {
                    $openzaak->catalogi()->eigenschappen()->store([
                        'zaaktype' => $zaaktype->zgw_zaaktype_url,
                        'naam' => $naam,
                        'definitie' => $definition['definitie'],
                        'specificatie' => $definition['specificatie'],
                        'toelichting' => '',
                    ]);

                    $this->line("    Eigenschap toegevoegd: {$naam}");
                } catch (Throwable $exception) {
                    $this->warn("    Toevoegen van eigenschap '{$naam}' mislukt: {$exception->getMessage()}");
                    $eigenschapFailed = true;
                }
            }

            // Herpubliceren als het zaaktype eerst gepubliceerd was.
            if (! $isConcept) {
                $publishResponse = Http::withHeaders($headers)->post($baseUrl."zaaktypen/{$uuid}/publish");

                if (! $publishResponse->successful()) {
                    $this->warn("    Herpubliceren mislukt (HTTP {$publishResponse->status()}). Publiceer handmatig.");
                    $eigenschapFailed = true;
                } else {
                    $this->line('    Hergepubliceerd.');
                }
            }

            if ($eigenschapFailed) {
                $failed++;

                continue;
            }

            $updated++;
        }

        $this->newLine();
        $this->info("Klaar. Bijgewerkt: {$updated}, al correct: {$alreadyCorrect}, mislukt: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
