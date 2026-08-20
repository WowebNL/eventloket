<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Zgw\ZgwEndpoints;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes one log line for every 4xx/5xx response a ZGW API returns.
 *
 * Without it a failed ZGW call only surfaces as Laravel's RequestException, whose message
 * truncates the response body at 120 characters. That is usually just enough to see that
 * something failed and never enough to see which field the provider rejected, so the actual
 * reason for a rejected status, role or document is invisible in production.
 *
 * The listener hooks the HTTP client rather than the call sites. Every ZGW call in this
 * application goes through Laravel's HTTP client: the woweb/openzaak package builds each
 * request with `Http::withHeaders(...)`, the woweb/laravel-zgw-client package does the same in
 * `ZgwConnection::request()`, and the handful of console commands that talk to the catalogi API
 * directly use the `Http` facade too. One listener on `ResponseReceived` therefore covers all
 * of them, including call sites added later.
 *
 * What is logged is a strict whitelist, because the VNG error body echoes submitted values and
 * this line also ends up in Sentry as a breadcrumb:
 *  - the HTTP status, the request method and the URL without its query string;
 *  - the `Api-Version` response header, which tells you which ZGW release answered;
 *  - from the standard VNG error body only `code`, `title`, `status` and `invalidParams[]`
 *    (per entry `name`, `code` and `reason`).
 *
 * Never logged: the raw body, `detail`, `instance`, any other body field, the request payload,
 * the query string or any submitted value. A body that is not a JSON object, or one without a
 * single whitelisted field, is reduced to its content type and byte length.
 *
 * This is observability only. The response is untouched and the exception the caller sees is
 * exactly the one it saw before.
 *
 * Registration happens through Laravel's automatic listener discovery, which picks the class up
 * from app/Listeners by the type hint on handle(). Adding an explicit Event::listen() call would
 * register it a second time and write every line twice.
 */
final class LogZgwErrorResponse
{
    /**
     * Guard rails against a hostile or broken provider filling the log in one response.
     */
    private const MAX_INVALID_PARAMS = 50;

    private const MAX_VALUE_LENGTH = 500;

    /**
     * @var list<string>
     */
    private const ERROR_FIELDS = ['code', 'title', 'status'];

    /**
     * @var list<string>
     */
    private const INVALID_PARAM_FIELDS = ['name', 'code', 'reason'];

    public function handle(ResponseReceived $event): void
    {
        try {
            $response = $event->response;

            if (! $response->failed()) {
                return;
            }

            $url = $event->request->url();

            if (! ZgwEndpoints::isZgwUrl($url)) {
                return;
            }

            $context = [
                'status' => $response->status(),
                'method' => $event->request->method(),
                'url' => $this->withoutQueryString($url),
            ];

            $apiVersion = $response->header('Api-Version');

            if ($apiVersion !== '') {
                $context['api_version'] = $apiVersion;
            }

            Log::error('ZGW request failed.', $context + $this->describeBody($response));
        } catch (Throwable $e) {
            // Observability must never break the call it observes.
            Log::warning('Failed to log a failed ZGW request.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * The whitelisted fields of a standard VNG error body, or a content type plus byte length
     * when the body carries none of them (an HTML error page, a proxy response, an empty body).
     *
     * @return array<string, mixed>
     */
    private function describeBody(Response $response): array
    {
        $decoded = $response->json();

        if (is_array($decoded)) {
            $fields = $this->whitelistedErrorFields($decoded);

            if ($fields !== []) {
                return ['error' => $fields];
            }
        }

        return ['body' => [
            'content_type' => $response->header('Content-Type') ?: null,
            'length' => strlen($response->body()),
        ]];
    }

    /**
     * @param  array<mixed>  $body
     * @return array<string, mixed>
     */
    private function whitelistedErrorFields(array $body): array
    {
        $fields = [];

        foreach (self::ERROR_FIELDS as $field) {
            $value = $this->scalar($body[$field] ?? null);

            if ($value !== null) {
                $fields[$field] = $value;
            }
        }

        $invalidParams = $this->whitelistedInvalidParams($body['invalidParams'] ?? null);

        if ($invalidParams !== []) {
            $fields['invalid_params'] = $invalidParams;
        }

        return $fields;
    }

    /**
     * @return list<array<string, scalar>>
     */
    private function whitelistedInvalidParams(mixed $invalidParams): array
    {
        if (! is_array($invalidParams)) {
            return [];
        }

        $whitelisted = [];

        foreach ($invalidParams as $invalidParam) {
            if (count($whitelisted) >= self::MAX_INVALID_PARAMS) {
                break;
            }

            if (! is_array($invalidParam)) {
                continue;
            }

            $entry = [];

            foreach (self::INVALID_PARAM_FIELDS as $field) {
                $value = $this->scalar($invalidParam[$field] ?? null);

                if ($value !== null) {
                    $entry[$field] = $value;
                }
            }

            if ($entry !== []) {
                $whitelisted[] = $entry;
            }
        }

        return $whitelisted;
    }

    /**
     * Keep scalars only, so a nested object smuggled into a whitelisted field cannot end up in
     * the log, and cap the length of free-form text such as a validation reason.
     */
    private function scalar(mixed $value): string|int|float|bool|null
    {
        if (is_string($value)) {
            return Str::limit($value, self::MAX_VALUE_LENGTH);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return null;
    }

    /**
     * ZGW filters carry personal data (a BSN, an address, a case identifier), so the query
     * string is dropped before the URL is logged.
     */
    private function withoutQueryString(string $url): string
    {
        return Str::before(Str::before($url, '?'), '#');
    }
}
