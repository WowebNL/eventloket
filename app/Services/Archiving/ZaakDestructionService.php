<?php

namespace App\Services\Archiving;

use App\ValueObjects\OzZaak;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Connection\ObjectsApiConnection;
use Woweb\Openzaak\Connection\OpenzaakConnection;
use Woweb\Openzaak\Openzaak;

/**
 * Executes the destruction of a single zaak in OpenZaak: its besluiten,
 * its documents (unless shared with other objects) and finally the zaak
 * itself, plus the form submission object in the Objects API.
 *
 * Every step treats a 404 as success, so a partially failed destruction can
 * be retried safely.
 *
 * All ZGW calls are kept inside this class so the migration to
 * woweb/laravel-zgw-client (#1) only has to touch this file.
 */
class ZaakDestructionService
{
    private OpenzaakConnection $connection;

    private Openzaak $openzaak;

    public function __construct()
    {
        $this->connection = new OpenzaakConnection;
        $this->openzaak = new Openzaak($this->connection);
    }

    /**
     * Fetch the zaak fresh (uncached) from OpenZaak, or null when it no longer exists.
     */
    public function fetchZaak(string $zaakUrl): ?OzZaak
    {
        $response = Http::withHeaders($this->connection->getHeaders())->get($zaakUrl);

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return new OzZaak(...$response->json());
    }

    public function isEligibleForDestruction(OzZaak $zaak): bool
    {
        return $zaak->archiefnominatie === 'vernietigen'
            && $zaak->archiefactiedatum_datetime !== null
            && $zaak->archiefactiedatum_datetime->isPast();
    }

    /**
     * @return array{skipped_documents: array<string>}
     */
    public function destroy(string $zaakUrl): array
    {
        $skippedDocuments = [];

        // Besluiten and their documents have to go before the zaak itself.
        $besluiten = $this->openzaak->besluiten()->besluiten()->getAll(['zaak' => $zaakUrl]);

        foreach ($besluiten as $besluit) {
            $besluitinformatieobjecten = $this->openzaak->besluiten()->besluitinformatieobjecten()
                ->getAll(['besluit' => $besluit['url']]);

            foreach ($besluitinformatieobjecten as $besluitinformatieobject) {
                $this->delete($besluitinformatieobject['url']);
                $this->deleteDocumentIfUnused($besluitinformatieobject['informatieobject'], $skippedDocuments);
            }

            $this->delete($besluit['url']);
        }

        $zaakinformatieobjecten = $this->openzaak->zaken()->zaakinformatieobjecten()
            ->getAll(['zaak' => $zaakUrl]);

        foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
            $this->delete($zaakinformatieobject['url']);
            $this->deleteDocumentIfUnused($zaakinformatieobject['informatieobject'], $skippedDocuments);
        }

        // OpenZaak cascades statussen, rollen, resultaat and zaakobjecten itself.
        $this->delete($zaakUrl);

        return ['skipped_documents' => $skippedDocuments];
    }

    /**
     * Delete a document unless it is still used by other zaken or besluiten.
     * The registry of objectinformatieobjecten is checked after the link to
     * the destroyed zaak/besluit is removed, so any remaining relation means
     * the document is shared and must be kept.
     */
    private function deleteDocumentIfUnused(string $documentUrl, array &$skippedDocuments): void
    {
        $remainingRelations = $this->openzaak->documenten()->objectinformatieobjecten()
            ->getAll(['informatieobject' => $documentUrl]);

        if ($remainingRelations->isNotEmpty()) {
            $skippedDocuments[] = $documentUrl;

            return;
        }

        $this->delete($documentUrl);
    }

    private function delete(string $url): void
    {
        $this->validateDeleteResponse(
            $url,
            Http::withHeaders($this->connection->getHeaders())->delete($url),
        );
    }

    public function deleteDataObject(string $url): void
    {
        $this->validateDeleteResponse(
            $url,
            Http::withHeaders((new ObjectsApiConnection)->getHeaders())->delete($url),
        );
    }

    private function validateDeleteResponse(string $url, Response $response): void
    {
        // Already gone: fine, destruction is idempotent.
        if ($response->status() === 404) {
            return;
        }

        if ($response->failed()) {
            Log::error("Destruction delete of [{$url}] failed: {$response->body()}");
            $response->throw();
        }
    }
}
