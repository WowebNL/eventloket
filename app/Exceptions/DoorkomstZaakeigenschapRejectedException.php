<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Reported when one zaakeigenschap could not be written onto a doorkomst
 * deelzaak.
 *
 * The deelzaak itself is created and the other eigenschappen, the initiator,
 * the documents and the status still follow, so this is reported rather than
 * thrown. It carries the catalogus naam of the eigenschap and a summary of the
 * error response, never the value that was sent: a value can hold personal
 * data, and a validator can quote it back in its reason text.
 */
class DoorkomstZaakeigenschapRejectedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response  summary of the error response, without reason texts or values
     */
    public function __construct(
        public readonly string $eigenschap,
        public readonly string $zaakUrl,
        public readonly array $response,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Zaakeigenschap "%s" could not be written onto doorkomst deelzaak %s%s.',
                $eigenschap,
                $zaakUrl,
                self::describeResponse($response),
            ),
            0,
            $previous,
        );
    }

    /**
     * The status and the invalid parameters in the message itself, so they
     * are visible wherever only the message is shown.
     *
     * @param  array<string, mixed>  $response
     */
    private static function describeResponse(array $response): string
    {
        $status = $response['status'] ?? null;
        if (! is_scalar($status)) {
            return '';
        }

        $params = [];
        foreach ((array) ($response['invalid_params'] ?? []) as $param) {
            if (is_array($param)) {
                $name = $param['name'] ?? null;
                $code = $param['code'] ?? null;
                $params[] = sprintf('%s: %s', is_scalar($name) ? (string) $name : '?', is_scalar($code) ? (string) $code : '?');
            }
        }

        return sprintf(' [%s]%s', (string) $status, $params === [] ? '' : ' ('.implode(', ', $params).')');
    }

    /**
     * Context added to the report of this exception.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'eigenschap' => $this->eigenschap,
            'zaak_url' => $this->zaakUrl,
            'response' => $this->response,
        ];
    }
}
