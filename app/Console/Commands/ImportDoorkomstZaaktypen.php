<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Woweb\Openzaak\Connection\OpenzaakConnection;

class ImportDoorkomstZaaktypen extends Command
{
    protected $signature = 'app:import-doorkomst-zaaktypen {--dry-run : Simuleer de import zonder daadwerkelijk iets aan te maken}';

    protected $description = 'Importeert Doorkomst zaaktypen (inclusief child resources) naar de gekoppelde Open Zaak catalogus';

    private array $headers = [];

    private string $baseUrl = '';

    private string $catalogusUrl = '';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $connection = new OpenzaakConnection;
        $this->headers = $connection->getHeaders();
        $this->baseUrl = rtrim(config('openzaak.url'), '/').'/catalogi/api/v1/';
        $this->catalogusUrl = config('openzaak.catalogi_url');

        if ($this->dryRun) {
            $this->warn('DRY-RUN modus: er worden geen wijzigingen doorgevoerd.');
        }

        $this->info('Open Zaak base URL: '.$this->baseUrl);
        $this->info('Catalogus URL: '.$this->catalogusUrl);

        // Load source JSON files
        $catalogusDir = base_path('docker/local-data/open-zaak-catalogus');
        $sourceZaaktypen = $this->loadJson($catalogusDir.'/ZaakType.json');
        $sourceStatustypen = $this->loadJson($catalogusDir.'/StatusType.json');
        $sourceRoltypen = $this->loadJson($catalogusDir.'/RolType.json');
        $sourceResultaattypen = $this->loadJson($catalogusDir.'/ResultaatType.json');
        $sourceEigenschappen = $this->loadJson($catalogusDir.'/Eigenschap.json');
        $sourceZtiots = $this->loadJson($catalogusDir.'/ZaakTypeInformatieObjectType.json');

        // Filter to Doorkomst zaaktypen only
        $doorkomstZaaktypen = array_values(array_filter(
            $sourceZaaktypen,
            fn ($zt) => str_contains($zt['identificatie'], 'Doorkomst')
        ));

        $this->info('Doorkomst zaaktypen gevonden in bronbestand: '.count($doorkomstZaaktypen));

        // Group child resources by source zaaktype URL
        $statustypenByZaaktype = $this->groupBy($sourceStatustypen, 'zaaktype');
        $roltypenByZaaktype = $this->groupBy($sourceRoltypen, 'zaaktype');
        $resultaattypenByZaaktype = $this->groupBy($sourceResultaattypen, 'zaaktype');
        $eigenschappenByZaaktype = $this->groupBy($sourceEigenschappen, 'zaaktype');

        // Filter ZTIOTs to Doorkomst only and group by zaaktype
        $doorkomstZtiots = array_filter(
            $sourceZtiots,
            fn ($z) => str_contains($z['zaaktype_identificatie'] ?? '', 'Doorkomst')
        );
        $ztiotsByZaaktype = $this->groupBy($doorkomstZtiots, 'zaaktype');

        // Fetch existing zaaktypen in catalogus for idempotency
        $this->info('Bestaande zaaktypen ophalen uit catalogus...');
        $existingZaaktypen = $this->fetchAll($this->baseUrl.'zaaktypen?catalogus='.urlencode($this->catalogusUrl));
        $existingIdentificaties = array_flip(array_column($existingZaaktypen, 'identificatie'));
        $this->info('Bestaande zaaktypen gevonden: '.count($existingZaaktypen));

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($doorkomstZaaktypen as $zt) {
            $identificatie = $zt['identificatie'];

            if (isset($existingIdentificaties[$identificatie])) {
                $this->line("Overgeslagen: $identificatie");
                $skipped++;

                continue;
            }

            $this->info("Aanmaken: $identificatie");

            $sourceZaaktypeUrl = $zt['url'];

            if ($this->dryRun) {
                $statustypenCount = count($statustypenByZaaktype[$sourceZaaktypeUrl] ?? []);
                $roltypenCount = count($roltypenByZaaktype[$sourceZaaktypeUrl] ?? []);
                $resultaatCount = count($resultaattypenByZaaktype[$sourceZaaktypeUrl] ?? []);
                $eigCount = count($eigenschappenByZaaktype[$sourceZaaktypeUrl] ?? []);
                $ztiotCount = count($ztiotsByZaaktype[$sourceZaaktypeUrl] ?? []);
                $besluittypen = $zt['besluittypen'] ?? [];

                $this->line('  [DRY-RUN] Zou aanmaken:');
                $this->line("    Statustypen:                  $statustypenCount");
                $this->line("    Roltypen:                     $roltypenCount");
                $this->line("    Resultaattypen:               $resultaatCount");
                $this->line("    Eigenschappen:                $eigCount");
                $this->line("    ZaakTypeInformatieObjectTypen: $ztiotCount");
                $this->line('    Besluittypen:                 '.(empty($besluittypen) ? '(geen)' : implode(', ', $besluittypen)));

                $created++;

                continue;
            }

            // Build zaaktype POST payload
            $payload = $this->buildZaaktypePayload($zt);

            // Aanpak A: POST met besluittypen
            $response = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen', $payload);

            if ($response->clientError()) {
                // Fallback: probeer zonder besluittypen
                $this->warn("  Fout bij aanmaken met besluittypen (HTTP {$response->status()}), opnieuw proberen zonder besluittypen...");
                unset($payload['besluittypen']);
                $response = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen', $payload);
            }

            if (! $response->successful()) {
                $this->error("  Mislukt: $identificatie — HTTP {$response->status()}: ".$response->body());
                $failed++;

                continue;
            }

            $newZaaktype = $response->json();
            $newZaaktypeUrl = $newZaaktype['url'];

            $this->line("  Zaaktype aangemaakt: $newZaaktypeUrl");

            // Besluittypen koppeling check
            if (! empty($zt['besluittypen']) && ! isset($payload['besluittypen'])) {
                $this->warn('  Besluittypen niet automatisch gekoppeld. Link handmatig: '.implode(', ', $zt['besluittypen']));
            }

            // Statustypen aanmaken en URL-mapping opbouwen
            $statustypenMap = [];
            foreach ($statustypenByZaaktype[$sourceZaaktypeUrl] ?? [] as $statustype) {
                $stPayload = [
                    'zaaktype' => $newZaaktypeUrl,
                    'omschrijving' => $statustype['omschrijving'],
                    'omschrijving_generiek' => $statustype['omschrijving_generiek'] ?? '',
                    'statustekst' => $statustype['statustekst'] ?? '',
                    'volgnummer' => $statustype['volgnummer'],
                    'is_eindstatus' => $statustype['is_eindstatus'],
                    'informeren' => $statustype['informeren'],
                    'doorlooptijd' => $statustype['doorlooptijd'],
                    'toelichting' => $statustype['toelichting'] ?? '',
                ];

                $stResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'statustypen', $stPayload);

                if ($stResponse->successful()) {
                    $statustypenMap[$statustype['url']] = $stResponse->json()['url'];
                } else {
                    $this->warn("  Statustype mislukt ({$statustype['omschrijving']}): HTTP {$stResponse->status()}");
                }
            }
            $this->line('  Statustypen aangemaakt: '.count($statustypenMap));

            // Roltypen aanmaken
            $rolCount = 0;
            foreach ($roltypenByZaaktype[$sourceZaaktypeUrl] ?? [] as $roltype) {
                $rtPayload = [
                    'zaaktype' => $newZaaktypeUrl,
                    'omschrijving' => $roltype['omschrijving'],
                    'omschrijving_generiek' => $roltype['omschrijving_generiek'],
                ];

                $rtResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'roltypen', $rtPayload);

                if ($rtResponse->successful()) {
                    $rolCount++;
                } else {
                    $this->warn("  Roltype mislukt ({$roltype['omschrijving']}): HTTP {$rtResponse->status()}");
                }
            }
            $this->line("  Roltypen aangemaakt: $rolCount");

            // Resultaattypen aanmaken
            $resultaatCount = 0;
            foreach ($resultaattypenByZaaktype[$sourceZaaktypeUrl] ?? [] as $resultaattype) {
                $resPayload = [
                    'zaaktype' => $newZaaktypeUrl,
                    'omschrijving' => $resultaattype['omschrijving'],
                    'resultaattypeomschrijving' => $resultaattype['resultaattypeomschrijving'],
                    'omschrijving_generiek' => $resultaattype['omschrijving_generiek'] ?? '',
                    'selectielijstklasse' => $resultaattype['selectielijstklasse'],
                    'toelichting' => $resultaattype['toelichting'] ?? '',
                    'archiefnominatie' => $resultaattype['archiefnominatie'],
                    'archiefactietermijn' => $resultaattype['archiefactietermijn'],
                    'brondatum_archiefprocedure' => $resultaattype['brondatum_archiefprocedure'],
                ];

                $resResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'resultaattypen', $resPayload);

                if ($resResponse->successful()) {
                    $resultaatCount++;
                } else {
                    $this->warn("  Resultaattype mislukt ({$resultaattype['omschrijving']}): HTTP {$resResponse->status()}");
                }
            }
            $this->line("  Resultaattypen aangemaakt: $resultaatCount");

            // Eigenschappen aanmaken
            $eigCount = 0;
            foreach ($eigenschappenByZaaktype[$sourceZaaktypeUrl] ?? [] as $eigenschap) {
                $sourceStatustypeUrl = $eigenschap['statustype'];
                $eigPayload = [
                    'zaaktype' => $newZaaktypeUrl,
                    'naam' => $eigenschap['naam'],
                    'definitie' => $eigenschap['definitie'],
                    'specificatie' => $eigenschap['specificatie'],
                    'toelichting' => $eigenschap['toelichting'] ?? '',
                    'statustype' => $sourceStatustypeUrl !== null
                        ? ($statustypenMap[$sourceStatustypeUrl] ?? null)
                        : null,
                ];

                $eigResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'eigenschappen', $eigPayload);

                if ($eigResponse->successful()) {
                    $eigCount++;
                } else {
                    $this->warn("  Eigenschap mislukt ({$eigenschap['naam']}): HTTP {$eigResponse->status()}");
                }
            }
            $this->line("  Eigenschappen aangemaakt: $eigCount");

            // ZaakTypeInformatieObjectTypen aanmaken
            $ztiotCount = 0;
            foreach ($ztiotsByZaaktype[$sourceZaaktypeUrl] ?? [] as $ztiot) {
                $sourceStatustypeUrl = $ztiot['statustype'];
                $ztiotPayload = [
                    'zaaktype' => $newZaaktypeUrl,
                    'informatieobjecttype' => $ztiot['informatieobjecttype'],
                    'volgnummer' => $ztiot['volgnummer'],
                    'richting' => $ztiot['richting'],
                    'statustype' => $sourceStatustypeUrl !== null
                        ? ($statustypenMap[$sourceStatustypeUrl] ?? null)
                        : null,
                ];

                $ztiotResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktype-informatieobjecttypen', $ztiotPayload);

                if ($ztiotResponse->successful()) {
                    $ztiotCount++;
                } else {
                    $this->warn("  ZTIOT mislukt (IOT: {$ztiot['informatieobjecttype']}): HTTP {$ztiotResponse->status()}");
                }
            }
            $this->line("  ZaakTypeInformatieObjectTypen aangemaakt: $ztiotCount");

            // Publiceer zaaktype
            $uuid = basename($newZaaktypeUrl);
            $publishResponse = Http::withHeaders($this->headers)->post($this->baseUrl."zaaktypen/{$uuid}/publish");

            if ($publishResponse->successful()) {
                $this->line('  Zaaktype gepubliceerd.');
                $created++;
            } else {
                $this->warn("  Publiceren mislukt: HTTP {$publishResponse->status()} — {$publishResponse->body()}");
                $created++;
            }
        }

        $this->newLine();

        if ($this->dryRun) {
            $this->warn("[DRY-RUN] Zou aanmaken: $created | Zou overslaan: $skipped | Zou mislukken: $failed");
        } else {
            $this->info("Klaar. Aangemaakt: $created | Overgeslagen: $skipped | Mislukt: $failed");
        }

        if (! $this->dryRun && $created > 0) {
            $this->newLine();
            $this->line('Voer vervolgens de volgende commando\'s uit:');
            $this->line('  php artisan app:sync-zaaktypen');
            $this->line('  php artisan app:link-zaaktypen-municipalities');
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function buildZaaktypePayload(array $zt): array
    {
        return [
            'identificatie' => $zt['identificatie'],
            'omschrijving' => $zt['omschrijving'],
            'omschrijving_generiek' => $zt['omschrijving_generiek'] ?? '',
            'vertrouwelijkheidaanduiding' => $zt['vertrouwelijkheidaanduiding'],
            'doel' => $zt['doel'],
            'aanleiding' => $zt['aanleiding'],
            'toelichting' => $zt['toelichting'] ?? '',
            'indicatie_intern_of_extern' => $zt['indicatie_intern_of_extern'],
            'handeling_initiator' => $zt['handeling_initiator'],
            'onderwerp' => $zt['onderwerp'],
            'handeling_behandelaar' => $zt['handeling_behandelaar'],
            'doorlooptijd' => $zt['doorlooptijd'],
            'servicenorm' => $zt['servicenorm'],
            'opschorting_en_aanhouding_mogelijk' => $zt['opschorting_en_aanhouding_mogelijk'],
            'verlenging_mogelijk' => $zt['verlenging_mogelijk'],
            'verlengingstermijn' => $zt['verlengingstermijn'],
            'trefwoorden' => $zt['trefwoorden'] ?? [],
            'publicatie_indicatie' => $zt['publicatie_indicatie'],
            'publicatietekst' => $zt['publicatietekst'] ?? '',
            'verantwoordingsrelatie' => $zt['verantwoordingsrelatie'] ?? [],
            'producten_of_diensten' => $zt['producten_of_diensten'] ?? [],
            'selectielijst_procestype' => $zt['selectielijst_procestype'],
            'referentieproces' => $zt['referentieproces'],
            'verantwoordelijke' => $zt['verantwoordelijke'] ?? '',
            'catalogus' => $this->catalogusUrl,
            'begin_geldigheid' => $zt['begin_geldigheid'],
            'einde_geldigheid' => $zt['einde_geldigheid'],
            'broncatalogus' => $zt['broncatalogus'],
            'bronzaaktype' => $zt['bronzaaktype'],
            'besluittypen' => $zt['besluittypen'] ?? [],
            'deelzaaktypen' => [],
            'gerelateerde_zaaktypen' => [],
            'zaakobjecttypen' => [],
        ];
    }

    /**
     * Fetch all pages of a paginated GET endpoint and return all results.
     */
    private function fetchAll(string $url): array
    {
        $results = [];
        $nextUrl = $url;

        while ($nextUrl !== null) {
            $response = Http::withHeaders($this->headers)->get($nextUrl);

            if (! $response->successful()) {
                $this->error("GET $nextUrl mislukt: HTTP {$response->status()}");
                break;
            }

            $data = $response->json();
            $results = array_merge($results, $data['results'] ?? []);
            $nextUrl = $data['next'] ?? null;
        }

        return $results;
    }

    /**
     * Group an array of records by a specific key's value.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBy(array $items, string $key): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $groupKey = $item[$key] ?? null;
            if ($groupKey !== null) {
                $grouped[$groupKey][] = $item;
            }
        }

        return $grouped;
    }

    private function loadJson(string $path): array
    {
        $json = file_get_contents($path);

        if ($json === false) {
            $this->error("Kan bestand niet lezen: $path");

            return [];
        }

        return json_decode($json, true) ?? [];
    }
}
