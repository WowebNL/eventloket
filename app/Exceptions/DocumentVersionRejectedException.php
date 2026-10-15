<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Woweb\Zgw\Exceptions\ApiRequestException;

/**
 * Reported when the Documenten API did not accept a new version of a document.
 *
 * The user gets a notification instead of the generic error page, so without
 * this report the failure would no longer reach error reporting at all. It
 * carries what is needed to tell a refused authorisation apart from an outage:
 * the ZGW connection, the HTTP status and the error code the API returned. The
 * response body and the file stay out of it on purpose, because a documents
 * API answer can carry the document's own metadata.
 */
class DocumentVersionRejectedException extends RuntimeException
{
    private const MAX_ERROR_CODE_LENGTH = 64;

    public function __construct(
        public readonly string $connectionName,
        public readonly string $documentUuid,
        public readonly ?int $status,
        public readonly ?string $errorCode,
        ApiRequestException|ConnectionException $previous,
    ) {
        parent::__construct(sprintf(
            'The Documenten API did not accept a new document version [%s, code %s] on ZGW connection "%s".',
            $status === null ? 'no response' : (string) $status,
            $errorCode ?? 'none',
            $connectionName,
        ), 0, $previous);
    }

    public static function fromFailure(ApiRequestException|ConnectionException $e, string $connectionName, string $documentUuid): self
    {
        $status = null;
        $errorCode = null;

        if ($e instanceof ApiRequestException) {
            $response = $e->getResponse();
            $status = $response->status();
            $body = $response->json();
            $code = is_array($body) ? ($body['code'] ?? null) : null;
            // The code comes from the API response; cap it so a misbehaving
            // server cannot push an arbitrarily long string into reports.
            $errorCode = is_scalar($code) ? mb_substr((string) $code, 0, self::MAX_ERROR_CODE_LENGTH) : null;
        }

        return new self($connectionName, $documentUuid, $status, $errorCode, $e);
    }

    /**
     * A 403 means the API refuses this client the operation: a missing
     * authorisation, not a temporary failure that a retry would solve.
     */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return [
            'connection' => $this->connectionName,
            'document_uuid' => $this->documentUuid,
            'status' => $this->status,
            'code' => $this->errorCode,
        ];
    }
}
