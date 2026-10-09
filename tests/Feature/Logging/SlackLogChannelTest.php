<?php

use Illuminate\Support\Facades\Log;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

const SYNTHETIC_EMAIL = 'synthetic.person@example.test';

beforeEach(function () {
    config([
        'app.env' => 'testing',
        'logging.channels.slack.url' => 'https://hooks.slack.invalid/services/test/webhook',
        'logging.channels.slack.level' => 'debug',
    ]);

    Log::forgetChannel('slack');
});

function slackHandler(): SlackWebhookHandler
{
    $handler = collect(Log::channel('slack')->getLogger()->getHandlers())
        ->first(fn ($handler) => $handler instanceof SlackWebhookHandler);

    expect($handler)->toBeInstanceOf(SlackWebhookHandler::class);

    return $handler;
}

/**
 * The JSON body the Slack handler would post for the given record, built by the real handler
 * (including its own processors) without performing the HTTP call.
 */
function slackPayloadFor(LogRecord $record): array
{
    $handler = slackHandler();

    $processed = (fn (LogRecord $record) => $this->processRecord($record))->call($handler, $record);

    return $handler->getSlackRecord()->getSlackData($processed);
}

function reportedException(): RuntimeException
{
    return new RuntimeException('Could not send the invitation to '.SYNTHETIC_EMAIL);
}

function recordFor(?Throwable $exception, string $message): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
        channel: 'testing',
        level: Level::Critical,
        message: $message,
        context: array_filter([
            'exception' => $exception,
            'userId' => 12345,
            'payload' => ['email' => SYNTHETIC_EMAIL, 'name' => 'Synthetic Person'],
        ]),
        extra: ['request' => ['input' => ['email' => SYNTHETIC_EMAIL]]],
    );
}

test('the slack record of a logged exception contains no message, trace, context or extra data', function () {
    $exception = reportedException();

    $payload = slackPayloadFor(recordFor($exception, $exception->getMessage()));
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    expect($json)
        ->not->toContain(SYNTHETIC_EMAIL)
        ->not->toContain('Could not send the invitation')
        ->not->toContain('Synthetic Person')
        ->not->toContain('12345')
        ->not->toContain('userId')
        ->not->toContain('payload')
        ->not->toContain('"trace"')
        ->not->toContain('#0 ');
});

test('the slack record names the level, exception class, source, environment and time', function () {
    $exception = reportedException();

    $attachment = slackPayloadFor(recordFor($exception, $exception->getMessage()))['attachments'][0];
    $fields = collect($attachment['fields'])->pluck('value', 'title');

    expect($attachment['text'])->toStartWith(RuntimeException::class.' in tests/Feature/Logging/SlackLogChannelTest.php:')
        ->and($fields['Level'])->toBe('CRITICAL')
        ->and($fields['Exception'])->toBe(RuntimeException::class)
        ->and($fields['Source'])->toBe('tests/Feature/Logging/SlackLogChannelTest.php:'.$exception->getLine())
        ->and($fields['Environment'])->toBe('testing')
        ->and($fields['Time'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($attachment['ts'])->toBe(1767323045);
});

test('a slack record without an exception does not forward the log message', function () {
    $payload = slackPayloadFor(recordFor(null, 'Manual entry about '.SYNTHETIC_EMAIL));
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    expect($json)->not->toContain(SYNTHETIC_EMAIL)
        ->not->toContain('Manual entry')
        ->and($payload['attachments'][0]['text'])->toContain('withheld');
});

test('the file channel in a stack with slack still logs the full message and context', function () {
    $path = storage_path('logs/slack-channel-test-'.uniqid().'.log');

    config([
        'logging.channels.single.path' => $path,
        // Keep the slack handler in the stack but out of the way, so no HTTP call is made.
        'logging.channels.slack.level' => 'emergency',
    ]);
    Log::forgetChannel('single');
    Log::forgetChannel('slack');

    $stack = Log::stack(['single', 'slack']);

    expect(collect($stack->getLogger()->getHandlers())->map(fn ($handler) => $handler::class)->all())
        ->toContain(StreamHandler::class, SlackWebhookHandler::class);

    $exception = reportedException();
    $stack->critical($exception->getMessage(), [
        'exception' => $exception,
        'payload' => ['email' => SYNTHETIC_EMAIL],
    ]);

    try {
        $contents = file_get_contents($path);
    } finally {
        @unlink($path);
    }

    expect($contents)
        ->toContain('Could not send the invitation to '.SYNTHETIC_EMAIL)
        ->toContain('"payload":{"email":"'.SYNTHETIC_EMAIL.'"}')
        ->toContain('[stacktrace]');
});
