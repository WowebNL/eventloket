<?php

namespace App\Console\Commands\Zaaktypen;

use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Console\Command;
use Throwable;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Facades\Zgw;

class SyncZaaktypeEigenschappen extends Command
{
    protected $signature = 'app:sync-zaaktype-eigenschappen';

    protected $description = 'Voegt ontbrekende eigenschappen toe aan ieder gesynct zaaktype in onze eigen Open Zaak; voor externe instanties alleen read-only validatie';

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

    public function handle(ZgwConnectionResolver $resolver): int
    {
        $this->info('Eigenschappen aanvullen voor gesynchroniseerde zaaktypen...');

        $zaaktypen = Zaaktype::where('is_active', true)
            ->whereNotNull('zgw_zaaktype_url')
            ->get();

        if ($zaaktypen->isEmpty()) {
            $this->warn('Geen actieve zaaktypen gevonden. Voer eerst app:sync-zaaktypen uit.');

            return Command::FAILURE;
        }

        $updated = 0;
        $alreadyCorrect = 0;
        $failed = 0;
        $validationGaps = 0;

        foreach ($zaaktypen as $zaaktype) {
            $connectionName = $zaaktype->zgwConnectionName();
            $connection = Zgw::connection($connectionName);

            $catalogiEigenschappen = $connection->catalogi()->eigenschappen()
                ->index(['zaaktype' => $zaaktype->zgw_zaaktype_url])
                ->collect()
                ->map(fn ($item) => EigenschapData::from($item));

            $missing = collect(self::REQUIRED_EIGENSCHAPPEN)
                ->keys()
                ->reject(fn (string $naam) => $catalogiEigenschappen->contains(fn (EigenschapData $eigenschap) => $eigenschap->naam === $naam))
                ->values();

            if ($missing->isEmpty()) {
                $this->line("  <comment>Al correct</comment>: {$zaaktype->name}");
                $alreadyCorrect++;

                continue;
            }

            // Externally managed instances are validated read-only: we report the
            // missing eigenschappen but never create them on a catalogus we do not own.
            if (! $resolver->isManaged($connectionName)) {
                $this->warn(
                    "  Ontbrekende eigenschappen op externe connectie '{$connectionName}' voor {$zaaktype->name}: ".
                    $missing->implode(', ').'. Maak deze handmatig aan in de externe catalogus (read-only validatie).'
                );
                $validationGaps++;

                continue;
            }

            $this->line("  Aanvullen: {$zaaktype->name} (".$missing->implode(', ').')');

            $eigenschapFailed = false;

            foreach ($missing as $naam) {
                $definition = self::REQUIRED_EIGENSCHAPPEN[$naam];

                try {
                    $connection->catalogi()->eigenschappen()->store([
                        'zaaktype' => $zaaktype->zgw_zaaktype_url,
                        'naam' => $naam,
                        'definitie' => $definition['definitie'],
                        'specificatie' => $definition['specificatie'],
                        'toelichting' => '',
                    ]);

                    $this->line("    Eigenschap toegevoegd: {$naam}");
                } catch (Throwable $exception) {
                    $this->warn(
                        "    Toevoegen van eigenschap '{$naam}' mislukt: {$exception->getMessage()}. ".
                        'Eigenschappen kunnen alleen aan een concept-zaaktype worden toegevoegd.'
                    );
                    $eigenschapFailed = true;
                }
            }

            if ($eigenschapFailed) {
                $failed++;

                continue;
            }

            $updated++;
        }

        $this->newLine();
        $this->info("Klaar. Bijgewerkt: {$updated}, al correct: {$alreadyCorrect}, mislukt: {$failed}, validatie-gaten (extern): {$validationGaps}.");

        return $failed === 0 && $validationGaps === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
