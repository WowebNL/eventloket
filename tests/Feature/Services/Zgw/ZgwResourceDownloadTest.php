<?php

declare(strict_types=1);

use App\Services\Zgw\ZgwResource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;

/**
 * A download endpoint hands over raw file content. The connection puts
 * `Accept: application/json` on every request, which such an endpoint cannot
 * satisfy, so both binary paths have to replace that header and check the
 * status before treating the body as the file.
 */
function documentDownloadUrl(string $uuid): string
{
    return ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid.'/download';
}

it('accepts any media type when downloading by url and returns the bytes', function () {
    $url = documentDownloadUrl('by-url');

    Http::fake([$url => Http::response('%PDF-1.4 document bytes', 200)]);

    expect(ZgwResource::downloadByUrl('main', $url))->toBe('%PDF-1.4 document bytes');

    Http::assertSent(fn (Request $request) => $request->url() === $url
        && $request->header('Accept') === ['*/*']);
});

/**
 * The HTTP client is configured not to throw on an error status, so without an
 * explicit check the body of a refused request is returned as if it were the
 * document, and it ends up in a zip entry or a mail attachment under the
 * document's own name.
 */
it('refuses a response that is not the file instead of returning its body', function () {
    $url = documentDownloadUrl('refused');

    Http::fake([$url => Http::response('application/octet-stream', 406)]);

    expect(fn () => ZgwResource::downloadByUrl('main', $url))
        ->toThrow(ApiRequestException::class);
});

it('surfaces a server error on a download by url', function () {
    $url = documentDownloadUrl('broken');

    Http::fake([$url => Http::response('', 404)]);

    expect(fn () => ZgwResource::downloadByUrl('main', $url))
        ->toThrow(ApiRequestException::class);
});

/**
 * The viewing and single-download path goes through the client's own
 * `download()`, which already replaces the header and checks the status. It is
 * asserted here so the two paths stay demonstrably equivalent.
 */
it('accepts any media type on the uuid based document download', function () {
    $url = documentDownloadUrl('viewer-uuid');

    Http::fake([$url.'*' => Http::response('%PDF-1.4 viewer bytes', 200)]);

    expect(ZgwResource::downloadDocument('main', 'viewer-uuid'))->toBe('%PDF-1.4 viewer bytes');

    Http::assertSent(fn (Request $request) => $request->header('Accept') === ['*/*']);
});

it('rejects a download by url outside the connection allowlist', function () {
    Http::fake();

    expect(fn () => ZgwResource::downloadByUrl('main', 'https://elders.example.org/download'))
        ->toThrow(DisallowedHostException::class);

    Http::assertNothingSent();
});
