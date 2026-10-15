<?php

declare(strict_types=1);

use App\Exceptions\DocumentVersionRejectedException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\ValidationException;

function apiFailureWithCode(string $code, int $status = 500): ApiRequestException
{
    $response = new Response(new Psr7Response($status, ['Content-Type' => 'application/json'], json_encode(['code' => $code])));

    return new ApiRequestException("ZGW API request failed [{$status}].", $response, $status);
}

function validationFailureWithParams(array $params): ValidationException
{
    $response = new Response(new Psr7Response(400, ['Content-Type' => 'application/json'], json_encode(['code' => 'invalid', 'invalidParams' => $params])));

    return new ValidationException('ZGW API request failed validation [400].', $response, 400);
}

test('it carries the status and error code of the failed request', function () {
    $rejection = DocumentVersionRejectedException::fromFailure(apiFailureWithCode('permission_denied', 403), 'main', 'doc-uuid', DocumentVersionRejectedException::STEP_LOCK);

    expect($rejection->status)->toBe(403)
        ->and($rejection->errorCode)->toBe('permission_denied')
        ->and($rejection->isForbidden())->toBeTrue()
        ->and($rejection->getMessage())->toContain('permission_denied');
});

test('it caps an oversized error code in the message and the context', function () {
    $rejection = DocumentVersionRejectedException::fromFailure(apiFailureWithCode(str_repeat('x', 10000)), 'main', 'doc-uuid', DocumentVersionRejectedException::STEP_LOCK);

    expect(mb_strlen((string) $rejection->errorCode))->toBeLessThanOrEqual(64)
        ->and(mb_strlen((string) $rejection->context()['code']))->toBeLessThanOrEqual(64)
        ->and($rejection->getMessage())->not->toContain(str_repeat('x', 65));
});

test('it keeps only a capped name and code of a capped number of invalid parameters', function () {
    $params = array_fill(0, 50, ['name' => str_repeat('n', 10000), 'code' => str_repeat('c', 10000), 'reason' => 'a reason text']);

    $rejection = DocumentVersionRejectedException::fromFailure(validationFailureWithParams($params), 'main', 'doc-uuid', DocumentVersionRejectedException::STEP_PATCH);

    expect($rejection->invalidParams)->toHaveCount(20)
        ->and(array_keys($rejection->invalidParams[0]))->toBe(['name', 'code'])
        ->and(mb_strlen($rejection->invalidParams[0]['name']))->toBe(64)
        ->and(mb_strlen($rejection->invalidParams[0]['code']))->toBe(64)
        ->and(json_encode($rejection->context()))->not->toContain('a reason text');
});
