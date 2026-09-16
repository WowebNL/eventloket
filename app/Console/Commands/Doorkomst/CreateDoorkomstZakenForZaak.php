<?php

namespace App\Console\Commands\Doorkomst;

use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Zaak;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CreateDoorkomstZakenForZaak extends Command
{
    protected $signature = 'zaak:create-doorkomst-zaken
        {public_id : The public ID of the zaak}
        {--connection= : The ZGW connection that issued the number, required when more than one zaak carries it}';

    protected $description = 'Dispatch the CreateDoorkomstZaken job for a specific zaak by public_id.';

    public function handle(): int
    {
        $publicId = (string) $this->argument('public_id');
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;

        $matches = $this->lookup($publicId, $connection);

        if ($matches->isEmpty()) {
            return $this->reportNotFound($publicId, $connection);
        }

        if ($matches->count() > 1) {
            return $this->reportAmbiguous($publicId, $matches);
        }

        /** @var Zaak $zaak */
        $zaak = $matches->first();

        if (! $zaak->zgw_zaak_url) {
            $this->error("Zaak with public_id {$publicId} has no zgw_zaak_url.");

            return self::FAILURE;
        }

        CreateDoorkomstZaken::dispatch($zaak);

        $this->info("Dispatched CreateDoorkomstZaken job for zaak {$publicId} on connection {$zaak->zgw_connection} ({$zaak->zgw_zaak_url}).");

        return self::SUCCESS;
    }

    /**
     * The zaken carrying this number, optionally narrowed to one ZGW connection.
     *
     * A zaaknummer is only unique within the ZGW instance that issued it, so the
     * number alone can match a zaak of more than one municipality. Each row
     * records its issuing connection, and that column is also what the unique
     * index on `zaken` is scoped to, so narrowing on it yields at most one row.
     *
     * Ordered so the listing of an ambiguous number is stable between runs.
     *
     * @return Collection<int, Zaak>
     */
    private function lookup(string $publicId, ?string $connection): Collection
    {
        return Zaak::query()
            ->where('public_id', $publicId)
            ->when($connection !== null, fn ($query) => $query->where('zgw_connection', $connection))
            ->orderBy('zgw_connection')
            ->orderBy('id')
            ->get();
    }

    /**
     * Nothing matched. When the number does exist on other connections, name
     * those: the operator then knows the number is right and only the
     * --connection value is not.
     */
    private function reportNotFound(string $publicId, ?string $connection): int
    {
        if ($connection === null) {
            $this->error("No zaak found with public_id: {$publicId}");

            return self::FAILURE;
        }

        $this->error("No zaak found with public_id {$publicId} on connection {$connection}.");

        $elsewhere = Zaak::query()
            ->where('public_id', $publicId)
            ->orderBy('zgw_connection')
            ->pluck('zgw_connection')
            ->unique()
            ->values();

        if ($elsewhere->isNotEmpty()) {
            $this->line('That number does exist on: '.$elsewhere->implode(', ').'.');
        }

        return self::FAILURE;
    }

    /**
     * Refuse instead of picking one.
     *
     * The job this command dispatches writes the zaak's documents into the ZGW
     * instance of every municipality the route passes through, so acting on the
     * wrong zaak moves data belonging to one municipality into the instance of
     * another. Silently taking the first row would make that outcome depend on
     * the database's row order, so an ambiguous number stops here and names
     * every candidate with the connection to pass.
     *
     * @param  Collection<int, Zaak>  $matches
     */
    private function reportAmbiguous(string $publicId, Collection $matches): int
    {
        $this->error("More than one zaak carries public_id {$publicId}; pass --connection to choose one:");

        foreach ($matches as $zaak) {
            $this->line("  - {$zaak->zgw_connection} (zaak {$zaak->id})");
        }

        return self::FAILURE;
    }
}
