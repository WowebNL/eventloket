<?php

namespace App\Console\Commands;

use App\Actions\Geospatial\CheckIntersects;
use App\Models\Municipality;
use App\Models\Zaaktype;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Woweb\Openzaak\Connection\OpenzaakConnection;

class AnalyseRouteZaken extends Command
{
    protected $signature = 'app:analyse-route-zaken';

    protected $description = 'Analyseert alle route zaken (triggers_route_check) en toont doorkruiste gemeenten en deelzaken';

    private array $headers = [];

    private string $zakenBaseUrl = '';

    public function handle(): int
    {
        $connection = new OpenzaakConnection;
        $this->headers = $connection->getHeaders();
        $this->zakenBaseUrl = rtrim(config('openzaak.url'), '/').'/zaken/api/v1/';

        // Collect all doorkomst zaaktype URLs to skip those zaken
        $doorkomstZaaktypeUrls = Municipality::with('doorkomstZaaktype')
            ->whereNotNull('doorkomst_zaaktype_id')
            ->get()
            ->map(fn (Municipality $m) => $m->doorkomstZaaktype?->zgw_zaaktype_url)
            ->filter()
            ->unique()
            ->flip()
            ->all();

        // Load all triggers_route_check zaaktypen from local DB
        $routeZaaktypen = Zaaktype::where('triggers_route_check', true)->get();

        if ($routeZaaktypen->isEmpty()) {
            $this->warn('Geen zaaktypen met triggers_route_check gevonden.');

            return Command::SUCCESS;
        }

        $this->info("Route zaaktypen gevonden: {$routeZaaktypen->count()}");

        // Fetch all zaken for those zaaktypen from ZGW (paginated)
        $allZaken = [];
        foreach ($routeZaaktypen as $zaaktype) {
            $this->line("Zaken ophalen voor zaaktype: {$zaaktype->name}");
            $zaken = $this->fetchAll($this->zakenBaseUrl.'zaken?zaaktype='.urlencode($zaaktype->zgw_zaaktype_url));
            $allZaken = array_merge($allZaken, $zaken);
        }

        $this->info('Totaal opgehaalde zaken: '.count($allZaken));

        // Set up geometry engine once
        $geometryEngine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($geometryEngine);

        $rows = [];
        $routeZaakCount = 0;

        foreach ($allZaken as $zaak) {
            // Skip doorkomst zaken
            if (isset($doorkomstZaaktypeUrls[$zaak['zaaktype'] ?? ''])) {
                continue;
            }

            // Detect route geometry
            $line = $this->extractLineString($zaak['zaakgeometrie'] ?? null);

            if (! $line) {
                continue;
            }

            $routeZaakCount++;
            $identificatie = $zaak['identificatie'] ?? $zaak['uuid'] ?? '?';
            $zaaktypeUrl = $zaak['zaaktype'] ?? '';
            $zaaktypeNaam = $routeZaaktypen->firstWhere('zgw_zaaktype_url', $zaaktypeUrl)?->name ?? $zaaktypeUrl;

            // Municipality intersection analysis
            $allIntersecting = $checkIntersects->checkIntersectsWithModels($line);
            $startMunicipalities = $checkIntersects->checkIntersectsWithModels($line->startPoint());
            $endMunicipalities = $checkIntersects->checkIntersectsWithModels($line->endPoint());

            $startBrkIds = $startMunicipalities->pluck('brk_identification')->unique();
            $endBrkIds = $endMunicipalities->pluck('brk_identification')->unique();
            $allBrkIds = $startBrkIds->merge($endBrkIds)->unique();

            $passingMunicipalities = $allIntersecting->reject(
                fn (Municipality $m) => $allBrkIds->contains($m->brk_identification)
            );

            $startNames = $startMunicipalities->pluck('name')->unique()->implode(', ');
            $endNames = $endMunicipalities->pluck('name')->unique()->implode(', ');
            $passingNames = $passingMunicipalities->pluck('name')->implode(', ');
            $multiGemeente = $passingMunicipalities->isNotEmpty() ? 'Ja' : 'Nee';

            // Fetch deelzaken
            $deelzaakNummers = $this->fetchDeelzaakNummers($zaak['url'] ?? '');

            $rows[] = [
                'identificatie' => $identificatie,
                'zaaktype' => $zaaktypeNaam,
                'start_gemeente' => $startNames ?: '-',
                'eind_gemeente' => $endNames ?: '-',
                'doorkomst_gemeenten' => $passingNames ?: '-',
                'multi_gemeente' => $multiGemeente,
                'deelzaken' => $deelzaakNummers ?: '-',
            ];
        }

        $this->info("Route zaken (na filtering): {$routeZaakCount}");

        if (empty($rows)) {
            $this->warn('Geen route zaken gevonden.');

            return Command::SUCCESS;
        }

        // Table output
        $headers = ['Zaak', 'Zaaktype', 'Startgemeente', 'Eindgemeente', 'Doorkomst gemeenten', 'Multi-gemeente', 'Deelzaken'];
        $tableRows = array_map(fn ($r) => array_values($r), $rows);
        $this->table($headers, $tableRows);

        // CSV output
        $this->writeCsv($rows);

        return Command::SUCCESS;
    }

    /**
     * Extract a LineString from a zaakgeometrie array.
     * Handles both `type: LineString` and `type: GeometryCollection` containing a LineString.
     */
    private function extractLineString(?array $zaakgeometrie): ?LineString
    {
        if (! $zaakgeometrie) {
            return null;
        }

        $reader = new GeoJsonReader;

        try {
            if (($zaakgeometrie['type'] ?? '') === 'LineString') {
                $geom = $reader->read(json_encode($zaakgeometrie));

                return $geom instanceof LineString ? $geom : null;
            }

            if (($zaakgeometrie['type'] ?? '') === 'GeometryCollection') {
                foreach ($zaakgeometrie['geometries'] ?? [] as $subGeom) {
                    if (($subGeom['type'] ?? '') === 'LineString') {
                        $geom = $reader->read(json_encode($subGeom));

                        return $geom instanceof LineString ? $geom : null;
                    }
                }
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }

    /**
     * Fetch deelzaken identificaties for a zaak URL.
     */
    private function fetchDeelzaakNummers(string $zaakUrl): string
    {
        if (! $zaakUrl) {
            return '';
        }

        $response = Http::withHeaders($this->headers)->get($zaakUrl.'?expand=deelzaken');

        if (! $response->successful()) {
            return '';
        }

        $deelzaken = $response->json('_expand.deelzaken') ?? $response->json('deelzaken') ?? [];

        if (empty($deelzaken)) {
            return '';
        }

        return collect($deelzaken)
            ->pluck('identificatie')
            ->filter()
            ->implode(', ');
    }

    /**
     * Fetch all paginated results from a ZGW endpoint.
     */
    private function fetchAll(string $url): array
    {
        $results = [];
        $nextUrl = $url;

        while ($nextUrl !== null) {
            $response = Http::withHeaders($this->headers)->get($nextUrl);

            if (! $response->successful()) {
                $this->error("GET {$nextUrl} mislukt: HTTP {$response->status()}");
                break;
            }

            $data = $response->json();
            $results = array_merge($results, $data['results'] ?? []);
            $nextUrl = $data['next'] ?? null;
        }

        return $results;
    }

    /**
     * Write results to a CSV file in storage/app/temp/.
     */
    private function writeCsv(array $rows): void
    {
        Storage::makeDirectory('temp');

        $filename = 'temp/route-zaken-analyse-'.now()->format('Y-m-d').'.csv';
        $path = Storage::path($filename);

        $handle = fopen($path, 'w');
        fputcsv($handle, ['Zaak', 'Zaaktype', 'Startgemeente', 'Eindgemeente', 'Doorkomst gemeenten', 'Multi-gemeente', 'Deelzaken']);

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);

        $this->info("CSV weggeschreven naar: {$filename}");
    }
}
