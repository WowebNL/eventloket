<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

beforeEach(function () {
    // Write to a null handler: the assertions read the MessageLogged events, not a log file.
    config([
        'logging.default' => 'null',
        'openzaak.url' => 'https://zgw.example.com/',
        'openzaak.zaken_base_url' => null,
        'openzaak.catalogi_base_url' => null,
        'openzaak.documenten_base_url' => null,
        'openzaak.besluiten_base_url' => null,
        'openzaak.catalogi_url' => null,
        'openzaak.openklant.url' => null,
        'openzaak.objectsapi.url' => null,
        'zgw.connections' => [],
    ]);
});

/**
 * Run the callback while recording every log record the application writes.
 *
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function recordedLogs(Closure $callback): array
{
    $records = [];

    Log::listen(function (MessageLogged $logged) use (&$records) {
        $records[] = ['level' => $logged->level, 'message' => $logged->message, 'context' => $logged->context];
    });

    $callback();

    return $records;
}

/**
 * The records this listener wrote, ignoring anything logged by other code.
 *
 * @param  list<array{level: string, message: string, context: array<string, mixed>}>  $records
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function zgwErrorLogs(array $records): array
{
    return array_values(array_filter($records, fn (array $record) => $record['message'] === 'ZGW request failed.'));
}

/**
 * A complete VNG error body, including the fields that must never be logged.
 *
 * @param  array<int, array<string, string>>  $invalidParams
 * @return array<string, mixed>
 */
function vngErrorBody(array $invalidParams = [], string $detail = 'Ongeldige invoer.'): array
{
    return [
        'type' => 'https://zgw.example.com/ref/fouten/ValidationError/',
        'code' => 'invalid',
        'title' => 'Invalid input.',
        'status' => 400,
        'detail' => $detail,
        'instance' => 'urn:uuid:1f0a5f0a-0000-4000-8000-000000000000',
        'invalidParams' => $invalidParams,
    ];
}

test('a 400 with a VNG error body produces exactly one whitelisted log line', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response(
        vngErrorBody([[
            'name' => 'statustype',
            'code' => 'does-not-exist',
            'reason' => 'Het statustype hoort niet bij het zaaktype van de zaak.',
        ]]),
        400,
        ['Api-Version' => '1.5.1', 'Content-Type' => 'application/json'],
    )]);

    $records = recordedLogs(fn () => Http::post('https://zgw.example.com/zaken/api/v1/statussen', ['zaak' => 'x']));

    expect($records)->toHaveCount(1)
        ->and($records[0]['level'])->toBe('error')
        ->and($records[0]['message'])->toBe('ZGW request failed.')
        ->and($records[0]['context'])->toBe([
            'status' => 400,
            'method' => 'POST',
            'url' => 'https://zgw.example.com/zaken/api/v1/statussen',
            'api_version' => '1.5.1',
            'error' => [
                'code' => 'invalid',
                'title' => 'Invalid input.',
                'status' => 400,
                'invalid_params' => [[
                    'name' => 'statustype',
                    'code' => 'does-not-exist',
                    'reason' => 'Het statustype hoort niet bij het zaaktype van de zaak.',
                ]],
            ],
        ]);
});

test('the log line never carries personal data from the response body', function () {
    $body = vngErrorBody(
        [['name' => 'betrokkeneIdentificatie.inpBsn', 'code' => 'invalid', 'reason' => 'Ongeldig.']],
        detail: 'De opgegeven waarde 123456782 voor inpBsn is ongeldig voor Jan de Vries.',
    );
    $body['bsn'] = '123456782';
    $body['naam'] = 'Jan de Vries';

    Http::fake(['https://zgw.example.com/*' => Http::response($body, 400, ['Api-Version' => '1.5.1'])]);

    $records = recordedLogs(fn () => Http::post('https://zgw.example.com/zaken/api/v1/rollen', [
        'betrokkeneIdentificatie' => ['inpBsn' => '123456782', 'geslachtsnaam' => 'de Vries'],
    ]));

    $logged = json_encode($records, JSON_UNESCAPED_UNICODE);

    expect($records)->toHaveCount(1)
        ->and($records[0]['context']['error'])->not->toHaveKeys(['detail', 'instance', 'type', 'bsn', 'naam'])
        ->and($logged)->not->toContain('123456782')
        ->and($logged)->not->toContain('Jan de Vries')
        ->and($logged)->not->toContain('geslachtsnaam')
        ->and($logged)->not->toContain('urn:uuid:1f0a5f0a')
        ->and($records[0]['context']['error']['invalid_params'])->toBe([
            ['name' => 'betrokkeneIdentificatie.inpBsn', 'code' => 'invalid', 'reason' => 'Ongeldig.'],
        ]);
});

test('the query string is dropped from the logged url', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response(vngErrorBody(), 400)]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/rollen', [
        'betrokkeneIdentificatie__natuurlijkPersoon__inpBsn' => '123456782',
    ]));

    expect($records[0]['context']['url'])->toBe('https://zgw.example.com/zaken/api/v1/rollen')
        ->and(json_encode($records))->not->toContain('123456782');
});

test('a body that is not a JSON error object is reduced to its content type and length', function () {
    $html = '<html><body>502 Bad Gateway — upstream openzaak-web-1 refused the connection</body></html>';

    Http::fake(['https://zgw.example.com/*' => Http::response($html, 502, ['Content-Type' => 'text/html; charset=utf-8'])]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken/1234'));

    expect($records)->toHaveCount(1)
        ->and($records[0]['context'])->toBe([
            'status' => 502,
            'method' => 'GET',
            'url' => 'https://zgw.example.com/zaken/api/v1/zaken/1234',
            'body' => ['content_type' => 'text/html; charset=utf-8', 'length' => strlen($html)],
        ])
        ->and(json_encode($records))->not->toContain('Bad Gateway');
});

test('a JSON body without any whitelisted field is reduced to its content type and length', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response(
        ['detail' => 'Authenticatiegegevens zijn niet opgegeven.'],
        401,
        ['Content-Type' => 'application/json'],
    )]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken'));

    expect($records)->toHaveCount(1)
        ->and($records[0]['context'])->toHaveKey('body')
        ->and($records[0]['context'])->not->toHaveKey('error')
        ->and(json_encode($records))->not->toContain('Authenticatiegegevens');
});

test('the api version header is omitted when the provider does not send it', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response(vngErrorBody(), 400)]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken'));

    expect($records[0]['context'])->not->toHaveKey('api_version');
});

test('a nested object smuggled into a whitelisted field is dropped', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response([
        'code' => ['nested' => 'Jan de Vries'],
        'title' => 'Invalid input.',
        'status' => 400,
        'invalidParams' => [
            ['name' => 'zaak', 'code' => 'invalid', 'reason' => ['nested' => '123456782']],
            'not-an-object',
        ],
    ], 400)]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken'));

    expect($records[0]['context']['error'])->toBe([
        'title' => 'Invalid input.',
        'status' => 400,
        'invalid_params' => [['name' => 'zaak', 'code' => 'invalid']],
    ]);
});

test('free-form text is capped so one response cannot flood the log', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response([
        'code' => 'invalid',
        'title' => str_repeat('a', 2000),
        'status' => 400,
    ], 400)]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken'));

    expect(strlen($records[0]['context']['error']['title']))->toBeLessThanOrEqual(510);
});

test('at most fifty invalid params are logged', function () {
    $invalidParams = array_map(
        fn (int $i) => ['name' => "veld-{$i}", 'code' => 'invalid', 'reason' => 'Ongeldig.'],
        range(1, 120),
    );

    Http::fake(['https://zgw.example.com/*' => Http::response(vngErrorBody($invalidParams), 400)]);

    $records = recordedLogs(fn () => Http::get('https://zgw.example.com/zaken/api/v1/zaken'));

    expect($records[0]['context']['error']['invalid_params'])->toHaveCount(50);
});

test('a successful response is not logged and is returned unchanged', function () {
    Http::fake(['https://zgw.example.com/*' => Http::response(['url' => 'https://zgw.example.com/zaken/api/v1/zaken/1234'], 201)]);

    $response = null;
    $records = recordedLogs(function () use (&$response) {
        $response = Http::post('https://zgw.example.com/zaken/api/v1/zaken', ['bronorganisatie' => '000000000']);
    });

    expect($records)->toBe([])
        ->and($response->status())->toBe(201)
        ->and($response->json())->toBe(['url' => 'https://zgw.example.com/zaken/api/v1/zaken/1234']);
});

test('a failed call to a host that is not a ZGW api is not logged', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response('no_service', 404)]);

    $records = recordedLogs(fn () => Http::post('https://hooks.slack.com/services/T000/B000/secret-token', ['text' => 'hi']));

    expect($records)->toBe([]);
});

test('a failed call through the openzaak package is logged and still throws the same exception', function () {
    config([
        'openzaak.client_id' => 'eventloket',
        'openzaak.client_secret' => str_repeat('s', 32),
        'openzaak.user_jwt' => false,
    ]);

    Http::fake(['https://zgw.example.com/*' => Http::response(
        vngErrorBody([['name' => 'statustype', 'code' => 'invalid', 'reason' => 'Ongeldig statustype.']]),
        400,
        ['Api-Version' => '1.5.1'],
    )]);

    $exception = null;

    $records = recordedLogs(function () use (&$exception) {
        try {
            app(Openzaak::class)->zaken()->statussen()->store(['zaak' => 'https://zgw.example.com/zaken/api/v1/zaken/1234']);
        } catch (RequestException $e) {
            $exception = $e;
        }
    });

    expect($exception)->toBeInstanceOf(RequestException::class)
        ->and($exception->response->status())->toBe(400)
        ->and($exception->getMessage())->toStartWith('HTTP request returned status code 400')
        ->and(zgwErrorLogs($records))->toHaveCount(1)
        ->and(zgwErrorLogs($records)[0]['context']['error']['invalid_params'])->toBe([
            ['name' => 'statustype', 'code' => 'invalid', 'reason' => 'Ongeldig statustype.'],
        ]);
});
