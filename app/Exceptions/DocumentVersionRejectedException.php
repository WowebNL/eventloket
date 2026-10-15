<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\InvalidParam;
use Woweb\Zgw\Exceptions\ValidationException;

/**
 * Reported when a request to the Documenten API for a new version of a
 * document failed: the lock, the patch with the new content, or the unlock
 * that follows it.
 *
 * The user gets a notification instead of the generic error page, so without
 * this report the failure would no longer reach error reporting at all. It
 * carries what is needed to tell a refused authorisation apart from an outage
 * or a rejected request: the ZGW connection, the step, the HTTP status, the
 * error code the API returned and, for a validation error, the name and code
 * of each invalid parameter. The response body, the reason texts and the file
 * stay out of it on purpose, because a documents API answer can carry the
 * document's own metadata.
 */
class DocumentVersionRejectedException extends RuntimeException
{
    public const STEP_LOCK = 'lock';

    public const STEP_PATCH = 'patch';

    public const STEP_UNLOCK = 'unlock';

    /**
     * Values taken from the API response are capped, so a misbehaving server
     * cannot push arbitrarily long strings or lists into reports.
     */
    private const MAX_VALUE_LENGTH = 64;

    private const MAX_INVALID_PARAMS = 20;

    /**
     * @param  list<array{name: string, code: string}>  $invalidParams
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly string $documentUuid,
        public readonly string $step,
        public readonly ?int $status,
        public readonly ?string $errorCode,
        public readonly array $invalidParams,
        Throwable $previous,
    ) {
        parent::__construct(sprintf(
            'A Documenten API request for a new document version failed at the %s step [%s, code %s] on ZGW connection "%s".',
            $step,
            $status === null ? 'no response' : (string) $status,
            $errorCode ?? 'none',
            $connectionName,
        ), 0, $previous);
    }

    public static function fromFailure(Throwable $e, string $connectionName, string $documentUuid, string $step): self
    {
        $status = null;
        $errorCode = null;
        $invalidParams = [];

        if ($e instanceof ApiRequestException) {
            $response = $e->getResponse();
            $status = $response->status();
            $body = $response->json();
            $code = is_array($body) ? ($body['code'] ?? null) : null;
            $errorCode = is_scalar($code) ? self::cap((string) $code) : null;
        }

        if ($e instanceof ValidationException) {
            $invalidParams = array_map(
                static fn (InvalidParam $param): array => [
                    'name' => self::cap($param->name),
                    'code' => self::cap($param->code),
                ],
                array_slice($e->invalidParams(), 0, self::MAX_INVALID_PARAMS),
            );
        }

        return new self($connectionName, $documentUuid, $step, $status, $errorCode, $invalidParams, $e);
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
     * @return array{connection: string, document_uuid: string, step: string, status: ?int, code: ?string, invalid_params: list<array{name: string, code: string}>}
     */
    public function context(): array
    {
        return [
            'connection' => $this->connectionName,
            'document_uuid' => $this->documentUuid,
            'step' => $this->step,
            'status' => $this->status,
            'code' => $this->errorCode,
            'invalid_params' => $this->invalidParams,
        ];
    }

    private static function cap(string $value): string
    {
        return mb_substr($value, 0, self::MAX_VALUE_LENGTH);
    }
}
