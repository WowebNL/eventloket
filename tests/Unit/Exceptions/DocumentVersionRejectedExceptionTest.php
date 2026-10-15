<?php

declare(strict_types=1);

use App\Exceptions\DocumentVersionRejectedException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Woweb\Zgw\Exceptions\ApiRequestException;

function apiFailureWithCode(string $code, int $status = 500): ApiRequestException
{
    $response = new Response(new Psr7Response($status, ['Content-Type' => 'application/json'], json_encode(['code' => $code])));

    return new ApiRequestException("ZGW API request failed [{$status}].", $response, $status);
}

test('it carries the status and error code of the failed request', function () {
    $rejection = DocumentVersionRejectedException::fromFailure(apiFailureWithCode('permission_denied', 403), 'main', 'doc-uuid');

    expect($rejection->status)->toBe(403)
        ->and($rejection->errorCode)->toBe('permission_denied')
        ->and($rejection->isForbidden())->toBeTrue()
        ->and($rejection->getMessage())->toContain('permission_denied');
});

test('it caps an oversized error code in the message and the context', function () {
    $rejection = DocumentVersionRejectedException::fromFailure(apiFailureWithCode(str_repeat('x', 10000)), 'main', 'doc-uuid');

    expect(mb_strlen((string) $rejection->errorCode))->toBeLessThanOrEqual(64)
        ->and(mb_strlen((string) $rejection->context()['code']))->toBeLessThanOrEqual(64)
        ->and($rejection->getMessage())->not->toContain(str_repeat('x', 65));
});
