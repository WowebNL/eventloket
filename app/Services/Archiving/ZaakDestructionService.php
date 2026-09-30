<?php

namespace App\Services\Archiving;

use App\Exceptions\ZaakConnectionNotDestructibleException;
use App\Models\Zaak;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Connection\ObjectsApiConnection;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Archiefnominatie;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Facades\Zgw;

/**
 * Executes the destruction of a single zaak in OpenZaak: its besluiten,
 * its documents (unless shared with other objects) and finally the zaak
 * itself, plus the form submission object in the Objects API.
 *
 * Only zaken on the application's own OpenZaak (the "main" connection) are
 * destroyed here. A municipality that runs its own ZGW instance archives and
 * destroys in that instance; Eventloket only ever reads there. Every entry
 * point therefore passes through {@see assertDestructible()}.
 *
 * Every step treats a 404 as success, so a partially failed destruction can
 * be retried safely.
 *
 * The Objects API is the one call that still goes through woweb/openzaak: it is
 * not part of the ZGW standard and the ZGW client does not cover it.
 */
class ZaakDestructionService
{
    public function __construct(private readonly ZgwConnectionResolver $connections) {}

    /**
     * Fetch the zaak fresh from OpenZaak, or null when it no longer exists.
     *
     * Caching in the ZGW client is opt-in per call, so this read is uncached
     * without further ado — which is what the revalidation right before a
     * destruction needs.
     */
    public function fetchZaak(string $zaakUrl): ?ZaakReadModel
    {
        $this->assertDestructible($zaakUrl);

        try {
            return ZaakReadModel::fromArray(
                ZgwResource::byUrl(ZgwConnectionResolver::DEFAULT_CONNECTION, $zaakUrl)
            );
        } catch (ApiRequestException $exception) {
            if ($exception->getResponse()->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Whether OpenZaak still says this zaak may be destroyed.
     *
     * The archiefactiedatum is the day the retention period runs out, so the
     * zaak is destructible from the day after. The boundary is deliberately the
     * conservative one: destroying a day late costs nothing, destroying a day
     * early is unlawful and irreversible. It matches the finder's
     * `archiefactiedatum__lt` filter, which the coordinator's list is built from.
     */
    public function isEligibleForDestruction(ZaakReadModel $zaak): bool
    {
        return $zaak->archiefnominatie === Archiefnominatie::Vernietigen
            && $zaak->archiefactiedatum !== null
            && $zaak->archiefactiedatum->startOfDay()->isBefore(now()->startOfDay());
    }

    /**
     * Whether this zaak lives on our own OpenZaak and may therefore be destroyed
     * from here.
     *
     * The local zaak is the reliable signal (it resolves through its zaaktype,
     * which records the instance that hosts it). Soft-deleted zaken count: a
     * destruction list may well hold one. Without a local row the url host is
     * the only thing left to go on.
     */
    public function isDestructible(string $zaakUrl): bool
    {
        return $this->connectionFor($zaakUrl) === ZgwConnectionResolver::DEFAULT_CONNECTION;
    }

    /**
     * @throws ZaakConnectionNotDestructibleException
     */
    public function assertDestructible(string $zaakUrl): void
    {
        $connectionName = $this->connectionFor($zaakUrl);

        if ($connectionName !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
            throw new ZaakConnectionNotDestructibleException($zaakUrl, $connectionName);
        }
    }

    private function connectionFor(string $zaakUrl): string
    {
        $zaak = Zaak::withTrashed()->where('zgw_zaak_url', $zaakUrl)->first();

        if ($zaak !== null) {
            return $zaak->zgwConnectionName();
        }

        return $this->connections->forUrl($zaakUrl);
    }

    /**
     * @return array{skipped_documents: array<string>}
     */
    public function destroy(string $zaakUrl): array
    {
        $this->assertDestructible($zaakUrl);

        $skippedDocuments = [];

        // Besluiten and their documents have to go before the zaak itself.
        $besluiten = $this->zgw()->besluiten()->besluiten()->index(['zaak' => $zaakUrl]);

        foreach ($besluiten as $besluit) {
            $besluitinformatieobjecten = $this->zgw()->besluiten()->besluitinformatieobjecten()
                ->index(['besluit' => $besluit['url']]);

            foreach ($besluitinformatieobjecten as $besluitinformatieobject) {
                $this->deleteBesluitinformatieobject($besluitinformatieobject['url']);
                $this->deleteDocumentIfUnused($besluitinformatieobject['informatieobject'], $skippedDocuments);
            }

            $this->deleteBesluit($besluit['url']);
        }

        $zaakinformatieobjecten = $this->zgw()->zaken()->zaakinformatieobjecten()
            ->index(['zaak' => $zaakUrl]);

        foreach ($zaakinformatieobjecten as $zaakinformatieobject) {
            $this->deleteZaakinformatieobject($zaakinformatieobject['url']);
            $this->deleteDocumentIfUnused($zaakinformatieobject['informatieobject'], $skippedDocuments);
        }

        // OpenZaak cascades statussen, rollen, resultaat and zaakobjecten itself.
        $this->deleteZaak($zaakUrl);

        return ['skipped_documents' => $skippedDocuments];
    }

    /**
     * Delete a document unless it is still used by other zaken or besluiten.
     * The registry of objectinformatieobjecten is checked after the link to
     * the destroyed zaak/besluit is removed, so any remaining relation means
     * the document is shared and must be kept.
     *
     * The listing is realised with ->all() on purpose: index() is lazy, and a
     * shared document whose relations sit on the second page must not read as
     * unused.
     *
     * @param  array<string>  $skippedDocuments
     */
    private function deleteDocumentIfUnused(string $documentUrl, array &$skippedDocuments): void
    {
        $remainingRelations = $this->zgw()->documenten()->objectinformatieobjecten()
            ->index(['informatieobject' => $documentUrl])
            ->all();

        if ($remainingRelations !== []) {
            $skippedDocuments[] = $documentUrl;

            return;
        }

        $this->deleteDocument($documentUrl);
    }

    private function deleteZaak(string $url): void
    {
        $this->delete($url, fn (string $uuid) => $this->zgw()->zaken()->zaken()->delete($uuid));
    }

    private function deleteZaakinformatieobject(string $url): void
    {
        $this->delete($url, fn (string $uuid) => $this->zgw()->zaken()->zaakinformatieobjecten()->delete($uuid));
    }

    private function deleteBesluit(string $url): void
    {
        $this->delete($url, fn (string $uuid) => $this->zgw()->besluiten()->besluiten()->delete($uuid));
    }

    private function deleteBesluitinformatieobject(string $url): void
    {
        $this->delete($url, fn (string $uuid) => $this->zgw()->besluiten()->besluitinformatieobjecten()->delete($uuid));
    }

    private function deleteDocument(string $url): void
    {
        $this->delete($url, fn (string $uuid) => $this->zgw()->documenten()->enkelvoudiginformatieobjecten()->delete($uuid));
    }

    /**
     * Run one delete, addressing the resource by the uuid the client expects and
     * swallowing a 404 so destruction stays idempotent.
     *
     * @param  callable(string): bool  $delete
     */
    private function delete(string $url, callable $delete): void
    {
        try {
            $delete(basename(parse_url($url, PHP_URL_PATH) ?: $url));
        } catch (ApiRequestException $exception) {
            // Already gone: fine, destruction is idempotent.
            if ($exception->getResponse()->status() === 404) {
                return;
            }

            Log::error("Destruction delete of [{$url}] failed: {$exception->getResponse()->body()}");

            throw $exception;
        }
    }

    /**
     * Delete the form submission object in the Objects API.
     *
     * Still on woweb/openzaak: the Objects API is outside the ZGW standard and
     * therefore outside the ZGW client.
     */
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

    private function zgw(): ZgwConnection
    {
        return Zgw::connection(ZgwConnectionResolver::DEFAULT_CONNECTION);
    }
}
