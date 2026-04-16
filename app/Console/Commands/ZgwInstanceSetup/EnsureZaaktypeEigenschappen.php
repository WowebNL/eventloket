<?php

namespace App\Console\Commands\ZgwInstanceSetup;

use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Woweb\Openzaak\Connection\OpenzaakConnection;

class EnsureZaaktypeEigenschappen extends Command
{
    protected $signature = 'app:ensure-zaaktype-eigenschappen
                            {uuid : UUID van het bestaande zaaktype in Open Zaak}
                            {--dry-run : Simuleer de actie zonder daadwerkelijk iets aan te maken}
                            {--publish-uuid= : UUID van een reeds aangemaakt concept-zaaktype om direct te publiceren (slaat fasen 1-4 over)}';

    protected $description = 'Controleert of een zaaktype alle vereiste eigenschappen bevat en maakt indien nodig een nieuwe versie aan';

    private array $headers = [];

    private string $baseUrl = '';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $connection = new OpenzaakConnection;
        $this->headers = $connection->getHeaders();
        $this->baseUrl = rtrim(config('openzaak.url'), '/').'/catalogi/api/v1/';

        if ($this->dryRun) {
            $this->warn('DRY-RUN modus: er worden geen wijzigingen doorgevoerd.');
        }

        $uuid = $this->argument('uuid');
        $zaaktypeUrl = $this->baseUrl.'zaaktypen/'.$uuid;

        if ($conceptUuid = $this->option('publish-uuid')) {
            return $this->publishExistingConcept($uuid, $conceptUuid);
        }

        // ---------------------------------------------------------------
        // Phase 1 — Fetch zaaktype & check eigenschappen
        // ---------------------------------------------------------------
        $this->info("Zaaktype ophalen: $zaaktypeUrl");

        // dd($zaaktypeUrl);
        $zaaktypeResponse = Http::withHeaders($this->headers)->get($zaaktypeUrl);

        if (! $zaaktypeResponse->successful()) {
            $this->error("Zaaktype ophalen mislukt: HTTP {$zaaktypeResponse->status()} — {$zaaktypeResponse->body()}");

            return Command::FAILURE;
        }

        $zaaktype = $zaaktypeResponse->json();
        $this->line("Zaaktype: {$zaaktype['omschrijving']} ({$zaaktype['identificatie']})");

        $bestaandeEigenschappen = $this->fetchAll($this->baseUrl.'eigenschappen?zaaktype='.urlencode($zaaktypeUrl));
        $bestaandeNamen = array_column($bestaandeEigenschappen, 'naam');

        $configEigenschappen = config('app.eigenschappen', []);
        $configNamen = array_column($configEigenschappen, 'naam');

        $ontbrekendeNamen = array_values(array_diff($configNamen, $bestaandeNamen));

        if (empty($ontbrekendeNamen)) {
            $this->info('Alle vereiste eigenschappen zijn aanwezig. Geen actie nodig.');

            return Command::SUCCESS;
        }

        $this->warn('Ontbrekende eigenschappen ('.count($ontbrekendeNamen).'): '.implode(', ', $ontbrekendeNamen));

        // ---------------------------------------------------------------
        // Phase 2 — Fetch existing child resources
        // ---------------------------------------------------------------
        $this->info('Bestaande child resources ophalen...');

        $statustypen = $this->fetchAll($this->baseUrl.'statustypen?zaaktype='.urlencode($zaaktypeUrl));
        $roltypen = $this->fetchAll($this->baseUrl.'roltypen?zaaktype='.urlencode($zaaktypeUrl));
        $resultaattypen = $this->fetchAll($this->baseUrl.'resultaattypen?zaaktype='.urlencode($zaaktypeUrl));
        $ztiots = $this->fetchAll($this->baseUrl.'zaaktype-informatieobjecttypen?zaaktype='.urlencode($zaaktypeUrl));
        $zaakobjecttypen = $this->fetchAll($this->baseUrl.'zaakobjecttypen?zaaktype='.urlencode($zaaktypeUrl));

        $this->line('  Statustypen:                   '.count($statustypen));
        $this->line('  Roltypen:                      '.count($roltypen));
        $this->line('  Resultaattypen:                '.count($resultaattypen));
        $this->line('  ZaakTypeInformatieObjectTypen: '.count($ztiots));
        $this->line('  Zaakobjecttypen:               '.count($zaakobjecttypen));
        $this->line('  Bestaande eigenschappen:       '.count($bestaandeEigenschappen));
        $this->line('  Toe te voegen eigenschappen:   '.count($ontbrekendeNamen));

        // ---------------------------------------------------------------
        // Phase 3 — Create new zaaktype
        // ---------------------------------------------------------------
        $payload = $this->buildZaaktypePayload($zaaktype);

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('[DRY-RUN] Nieuw zaaktype zou worden aangemaakt met:');
            $this->line('  beginGeldigheid: '.$payload['beginGeldigheid']);
            $this->line('  eindeGeldigheid: '.($payload['eindeGeldigheid'] ?? '(leeg)'));

            return Command::SUCCESS;
        }

        $this->info('Nieuw zaaktype aanmaken...');

        $response = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen', $payload);

        if ($response->clientError()) {
            $this->warn("Fout bij aanmaken met besluittypen (HTTP {$response->status()}), opnieuw proberen zonder besluittypen...");
            unset($payload['besluittypen']);
            $response = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen', $payload);
        }

        if (! $response->successful()) {
            $this->error("Nieuw zaaktype aanmaken mislukt: HTTP {$response->status()} — {$response->body()}");

            return Command::FAILURE;
        }

        $newZaaktype = $response->json();
        $newZaaktypeUrl = $newZaaktype['url'];
        $newZaaktypeUuid = basename(rtrim($newZaaktypeUrl, '/'));

        $this->line("Zaaktype aangemaakt: $newZaaktypeUrl");

        if (! empty($zaaktype['besluittypen']) && ! isset($payload['besluittypen'])) {
            $this->warn('Besluittypen niet automatisch gekoppeld. Link handmatig: '.implode(', ', $zaaktype['besluittypen']));
        }

        // ---------------------------------------------------------------
        // Phase 4 — Create child resources
        // ---------------------------------------------------------------

        // Statustypen
        $statustypenMap = [];
        foreach ($statustypen as $statustype) {
            $stPayload = [
                'zaaktype' => $newZaaktypeUrl,
                'omschrijving' => $statustype['omschrijving'],
                'omschrijvingGeneriek' => $statustype['omschrijvingGeneriek'] ?? '',
                'statustekst' => $statustype['statustekst'] ?? '',
                'volgnummer' => $statustype['volgnummer'],
                'isEindstatus' => $statustype['isEindstatus'],
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
        $this->line('Statustypen aangemaakt: '.count($statustypenMap));

        // Roltypen
        $rolCount = 0;
        foreach ($roltypen as $roltype) {
            $rtResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'roltypen', [
                'zaaktype' => $newZaaktypeUrl,
                'omschrijving' => $roltype['omschrijving'],
                'omschrijvingGeneriek' => $roltype['omschrijvingGeneriek'],
            ]);

            if ($rtResponse->successful()) {
                $rolCount++;
            } else {
                $this->warn("  Roltype mislukt ({$roltype['omschrijving']}): HTTP {$rtResponse->status()}");
            }
        }
        $this->line("Roltypen aangemaakt: $rolCount");

        // Resultaattypen
        $resultaatCount = 0;
        foreach ($resultaattypen as $resultaattype) {
            $resResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'resultaattypen', [
                'zaaktype' => $newZaaktypeUrl,
                'omschrijving' => $resultaattype['omschrijving'],
                'resultaattypeomschrijving' => $resultaattype['resultaattypeomschrijving'],
                'omschrijvingGeneriek' => $resultaattype['omschrijvingGeneriek'] ?? '',
                'selectielijstklasse' => $resultaattype['selectielijstklasse'],
                'toelichting' => $resultaattype['toelichting'] ?? '',
                'archiefnominatie' => $resultaattype['archiefnominatie'],
                'archiefactietermijn' => $resultaattype['archiefactietermijn'],
                'brondatumArchiefprocedure' => $resultaattype['brondatumArchiefprocedure'],
            ]);

            if ($resResponse->successful()) {
                $resultaatCount++;
            } else {
                $this->warn("  Resultaattype mislukt ({$resultaattype['omschrijving']}): HTTP {$resResponse->status()}");
            }
        }
        $this->line("Resultaattypen aangemaakt: $resultaatCount");

        // Eigenschappen — bestaande kopiëren + ontbrekende uit config toevoegen
        $eigCount = 0;

        foreach ($bestaandeEigenschappen as $eigenschap) {
            $sourceStatustypeUrl = $eigenschap['statustype'] ?? null;
            $eigResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'eigenschappen', [
                'zaaktype' => $newZaaktypeUrl,
                'naam' => $eigenschap['naam'],
                'definitie' => $eigenschap['definitie'],
                'specificatie' => $eigenschap['specificatie'],
                'toelichting' => $eigenschap['toelichting'] ?? '',
                'statustype' => $sourceStatustypeUrl !== null
                    ? ($statustypenMap[$sourceStatustypeUrl] ?? null)
                    : null,
            ]);

            if ($eigResponse->successful()) {
                $eigCount++;
            } else {
                $this->warn("  Eigenschap mislukt ({$eigenschap['naam']}): HTTP {$eigResponse->status()}");
            }
        }

        // Ontbrekende config-eigenschappen toevoegen
        $configEigenMap = array_column($configEigenschappen, null, 'naam');
        foreach ($ontbrekendeNamen as $naam) {
            $configEig = $configEigenMap[$naam];
            $eigResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'eigenschappen', [
                'zaaktype' => $newZaaktypeUrl,
                'naam' => $configEig['naam'],
                'definitie' => $configEig['definitie'],
                'specificatie' => $configEig['specificatie'],
                'toelichting' => '',
                'statustype' => null,
            ]);

            if ($eigResponse->successful()) {
                $eigCount++;
            } else {
                $this->warn("  Config-eigenschap mislukt ({$naam}): HTTP {$eigResponse->status()} — {$eigResponse->body()}");
            }
        }
        $this->line("Eigenschappen aangemaakt: $eigCount (bestaand: ".count($bestaandeEigenschappen).', nieuw: '.count($ontbrekendeNamen).')');

        // ZaakTypeInformatieObjectTypen
        $ztiotCount = 0;
        foreach ($ztiots as $ztiot) {
            $sourceStatustypeUrl = $ztiot['statustype'] ?? null;
            $ztiotResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktype-informatieobjecttypen', [
                'zaaktype' => $newZaaktypeUrl,
                'informatieobjecttype' => $ztiot['informatieobjecttype'],
                'volgnummer' => $ztiot['volgnummer'],
                'richting' => $ztiot['richting'],
                'statustype' => $sourceStatustypeUrl !== null
                    ? ($statustypenMap[$sourceStatustypeUrl] ?? null)
                    : null,
            ]);

            if ($ztiotResponse->successful()) {
                $ztiotCount++;
            } else {
                $this->warn("  ZTIOT mislukt (IOT: {$ztiot['informatieobjecttype']}): HTTP {$ztiotResponse->status()}");
            }
        }
        $this->line("ZaakTypeInformatieObjectTypen aangemaakt: $ztiotCount");

        // Zaakobjecttypen
        $zaakobjecttypeCount = 0;
        foreach ($zaakobjecttypen as $zaakobjecttype) {
            $sourceStatustypeUrl = $zaakobjecttype['statustype'] ?? null;
            $zotResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'zaakobjecttypen', [
                'zaaktype' => $newZaaktypeUrl,
                'objecttype' => $zaakobjecttype['objecttype'],
                'relatieOmschrijving' => $zaakobjecttype['relatieOmschrijving'] ?? '',
                'anderObjecttype' => $zaakobjecttype['anderObjecttype'] ?? false,
                'statustype' => $sourceStatustypeUrl !== null
                    ? ($statustypenMap[$sourceStatustypeUrl] ?? null)
                    : null,
            ]);

            if ($zotResponse->successful()) {
                $zaakobjecttypeCount++;
            } else {
                $this->warn("  Zaakobjecttype mislukt ({$zaakobjecttype['objecttype']}): HTTP {$zotResponse->status()}");
            }
        }
        $this->line("Zaakobjecttypen aangemaakt: $zaakobjecttypeCount");

        // ---------------------------------------------------------------
        // Phase 5 — Confirm & publish
        // ---------------------------------------------------------------
        $this->newLine();
        $this->info('Meest recente response van het nieuwe zaaktype:');
        $this->newLine();

        $fetchedNew = Http::withHeaders($this->headers)->get($this->baseUrl.'zaaktypen/'.$newZaaktypeUuid);
        $this->line(json_encode($fetchedNew->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->newLine();

        if (! $this->confirm('Wil je het nieuwe zaaktype publiceren?')) {
            $this->warn('Publicatie overgeslagen. Het zaaktype staat nu als concept. Gebruik --publish-uuid='.$newZaaktypeUuid.' om later te publiceren.');

            return Command::SUCCESS;
        }

        $this->info('Eindegeldigheid zetten op huidig zaaktype ('.$uuid.')...');
        $this->closeZaaktype($uuid);

        $publishResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen/'.$newZaaktypeUuid.'/publish');

        if (! $publishResponse->successful()) {
            $this->error("Publiceren mislukt: HTTP {$publishResponse->status()} — {$publishResponse->body()}");

            return Command::FAILURE;
        }

        $this->info('Zaaktype gepubliceerd.');
        $this->newLine();
        $this->syncLocalZaaktype($zaaktypeUrl, $newZaaktypeUrl);

        return Command::SUCCESS;
    }

    private function buildZaaktypePayload(array $zt): array
    {
        return [
            'identificatie' => $zt['identificatie'],
            'omschrijving' => $zt['omschrijving'],
            'omschrijvingGeneriek' => $zt['omschrijvingGeneriek'] ?? '',
            'vertrouwelijkheidaanduiding' => $zt['vertrouwelijkheidaanduiding'],
            'doel' => $zt['doel'],
            'aanleiding' => $zt['aanleiding'],
            'toelichting' => $zt['toelichting'] ?? '',
            'indicatieInternOfExtern' => $zt['indicatieInternOfExtern'],
            'handelingInitiator' => $zt['handelingInitiator'],
            'onderwerp' => $zt['onderwerp'],
            'handelingBehandelaar' => $zt['handelingBehandelaar'],
            'doorlooptijd' => $zt['doorlooptijd'],
            'servicenorm' => $zt['servicenorm'],
            'opschortingEnAanhoudingMogelijk' => $zt['opschortingEnAanhoudingMogelijk'],
            'verlengingMogelijk' => $zt['verlengingMogelijk'],
            'verlengingstermijn' => $zt['verlengingstermijn'],
            'trefwoorden' => $zt['trefwoorden'] ?? [],
            'publicatieIndicatie' => $zt['publicatieIndicatie'],
            'publicatietekst' => $zt['publicatietekst'] ?? '',
            'verantwoordingsrelatie' => $zt['verantwoordingsrelatie'] ?? [],
            'productenOfDiensten' => $zt['productenOfDiensten'] ?? [],
            'selectielijstProcestype' => $zt['selectielijstProcestype'],
            'referentieproces' => $zt['referentieproces'],
            'verantwoordelijke' => $zt['verantwoordelijke'] ?? '',
            'catalogus' => $zt['catalogus'],
            'beginGeldigheid' => now()->toDateString(),
            'versiedatum' => now()->toDateString(),
            'eindeGeldigheid' => null,
            'broncatalogus' => $zt['broncatalogus'],
            'bronzaaktype' => $zt['bronzaaktype'],
            'besluittypen' => $zt['besluittypen'] ?? [],
            'deelzaaktypen' => [],
            'gerelateerdeZaaktypen' => [],
            'zaakobjecttypen' => [],
        ];
    }

    private function closeZaaktype(string $uuid): void
    {
        $patchResponse = Http::withHeaders($this->headers)->patch(
            $this->baseUrl.'zaaktypen/'.$uuid,
            ['eindeGeldigheid' => now()->subDay()->toDateString()]
        );

        if ($patchResponse->successful()) {
            $this->line('Eindegeldigheid gezet op: '.now()->subDay()->toDateString());
        } else {
            $this->warn("Eindegeldigheid zetten mislukt: HTTP {$patchResponse->status()} — {$patchResponse->body()}");
        }
    }

    private function publishExistingConcept(string $originalUuid, string $conceptUuid): int
    {
        $this->info('Concept zaaktype ophalen: '.$conceptUuid);
        $this->newLine();

        $fetchedNew = Http::withHeaders($this->headers)->get($this->baseUrl.'zaaktypen/'.$conceptUuid);

        if (! $fetchedNew->successful()) {
            $this->error("Concept zaaktype ophalen mislukt: HTTP {$fetchedNew->status()} — {$fetchedNew->body()}");

            return Command::FAILURE;
        }

        $this->line(json_encode($fetchedNew->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();

        if (! $this->confirm('Wil je dit concept zaaktype publiceren?')) {
            $this->warn('Publicatie overgeslagen.');

            return Command::SUCCESS;
        }

        $this->info('Eindegeldigheid zetten op huidig zaaktype ('.$originalUuid.')...');

        $this->closeZaaktype($originalUuid);

        $publishResponse = Http::withHeaders($this->headers)->post($this->baseUrl.'zaaktypen/'.$conceptUuid.'/publish');

        if (! $publishResponse->successful()) {
            $this->error("Publiceren mislukt: HTTP {$publishResponse->status()} — {$publishResponse->body()}");

            return Command::FAILURE;
        }

        $this->info('Zaaktype gepubliceerd.');
        $this->newLine();
        $originalZaaktypeUrl = $this->baseUrl.'zaaktypen/'.$originalUuid;
        $newConceptUrl = $this->baseUrl.'zaaktypen/'.$conceptUuid;
        $this->syncLocalZaaktype($originalZaaktypeUrl, $newConceptUrl);

        return Command::SUCCESS;
    }

    private function syncLocalZaaktype(string $oldUrl, string $newUrl): void
    {
        $zaaktype = Zaaktype::where('zgw_zaaktype_url', $oldUrl)->first();

        if ($zaaktype) {
            $zaaktype->zgw_zaaktype_url = $newUrl;
            $zaaktype->save();

            activity('zaaktypen')
                ->performedOn($zaaktype)
                ->withProperties(['old_url' => $oldUrl, 'new_url' => $newUrl])
                ->log('Zaaktype URL bijgewerkt naar nieuwe versie via app:ensure-zaaktype-eigenschappen');

            $this->info("Lokaal zaaktype bijgewerkt: {$zaaktype->name} ({$zaaktype->id})");
            $this->line("  Oude URL: $oldUrl");
            $this->line("  Nieuwe URL: $newUrl");

            return;
        }

        $this->warn('Zaaktype niet gevonden in lokale database voor URL: '.$oldUrl);
        $this->line('Een nieuw lokaal zaaktype aanmaken...');
        $this->newLine();

        $newZaaktypeResponse = Http::withHeaders($this->headers)->get($newUrl);
        $newZaaktypeData = $newZaaktypeResponse->json();
        $omschrijving = $newZaaktypeData['omschrijving'] ?? $newUrl;

        $municipalities = Municipality::orderBy('name')->pluck('name', 'id')->toArray();
        $municipalityChoice = $this->choice(
            'Welke gemeente moet worden gekoppeld aan dit zaaktype?',
            ['(geen)'] + array_values($municipalities),
            0
        );
        $municipalityId = null;
        if ($municipalityChoice !== '(geen)') {
            $municipalityId = array_search($municipalityChoice, $municipalities, true) ?: null;
        }

        $triggersRouteCheck = $this->confirm('Moet dit zaaktype een route check triggeren?', false);

        $allResultaattypen = $this->fetchAll($this->baseUrl.'resultaattypen?zaaktype='.urlencode($newUrl));
        $hiddenResultaatTypes = [];
        if (! empty($allResultaattypen)) {
            $omschrijvingen = array_column($allResultaattypen, 'omschrijving');
            $this->line('Beschikbare resultaattypen: '.implode(', ', $omschrijvingen));
            $hiddenInput = $this->ask('Welke resultaattypen moeten verborgen worden? (komma-gescheiden, of leeg laten voor geen)');
            if (! empty(trim((string) $hiddenInput))) {
                $hiddenResultaatTypes = array_map('trim', explode(',', $hiddenInput));
            }
        }

        $newLocalZaaktype = Zaaktype::create([
            'name' => $omschrijving,
            'zgw_zaaktype_url' => $newUrl,
            'is_active' => true,
            'municipality_id' => $municipalityId,
            'triggers_route_check' => $triggersRouteCheck,
            'hidden_resultaat_types' => $hiddenResultaatTypes,
        ]);

        activity('zaaktypen')
            ->performedOn($newLocalZaaktype)
            ->withProperties(['url' => $newUrl, 'municipality_id' => $municipalityId])
            ->log('Nieuw lokaal zaaktype aangemaakt via app:ensure-zaaktype-eigenschappen');

        $this->info("Nieuw lokaal zaaktype aangemaakt: {$newLocalZaaktype->name} ({$newLocalZaaktype->id})");
    }

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
}
