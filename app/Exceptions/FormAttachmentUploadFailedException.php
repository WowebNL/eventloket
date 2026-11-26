<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\InvalidParam;
use Woweb\Zgw\Exceptions\ValidationException;

/**
 * Reported when a single form attachment could not be stored in OpenZaak at
 * submission. The other attachments of the same application are still
 * uploaded, so without this report the failure would only be visible as a
 * missing document on the case.
 *
 * It carries what is needed to see why the API refused the file: the ZGW
 * connection, the step, the HTTP status, the error code and, for a validation
 * error, the name and code of each invalid parameter. The response body, the
 * reason texts, the file name and the content stay out of it on purpose,
 * because they can hold personal data.
 */
class FormAttachmentUploadFailedException extends RuntimeException
{
    public const STEP_DOCUMENT = 'enkelvoudiginformatieobject';

    public const STEP_LINK = 'zaakinformatieobject';

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
        public readonly string $zaakId,
        public readonly string $connectionName,
        public readonly string $step,
        public readonly ?int $status,
        public readonly ?string $errorCode,
        public readonly array $invalidParams,
        Throwable $previous,
    ) {
        parent::__construct(sprintf(
            'Uploading a form attachment to OpenZaak failed at the %s step [%s, code %s] on ZGW connection "%s".',
            $step,
            $status === null ? 'no response' : (string) $status,
            $errorCode ?? 'none',
            $connectionName,
        ), 0, $previous);
    }

    public static function fromFailure(Throwable $e, string $zaakId, string $connectionName, string $step): self
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

        return new self($zaakId, $connectionName, $step, $status, $errorCode, $invalidParams, $e);
    }

    /**
     * A 4xx answer means the API refuses this request as it is sent. Sending
     * the same file again gives the same answer, so a retry cannot help. The
     * exceptions are 408 (request timeout) and 429 (too many requests): those
     * say "not now" and a later attempt can succeed.
     */
    public function isPermanent(): bool
    {
        return $this->status !== null
            && $this->status >= 400
            && $this->status < 500
            && ! in_array($this->status, [408, 429], true);
    }

    /**
     * @return array{zaak_id: string, connection: string, step: string, status: ?int, code: ?string, invalid_params: list<array{name: string, code: string}>}
     */
    public function context(): array
    {
        return [
            'zaak_id' => $this->zaakId,
            'connection' => $this->connectionName,
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
