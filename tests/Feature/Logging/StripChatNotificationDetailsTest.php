<?php

use App\Logging\StripChatNotificationDetails;
use Illuminate\Log\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Psr\Log\NullLogger;

test('the tap refuses a logger without a processable handler', function () {
    $logger = new Logger(new Monolog('chat', [new NullHandler]));

    (new StripChatNotificationDetails)($logger);
})->throws(RuntimeException::class, 'The chat notification channel must use a processable Monolog handler.');

test('the tap refuses a logger that is not a Monolog instance', function () {
    $logger = new Logger(new NullLogger);

    (new StripChatNotificationDetails)($logger);
})->throws(RuntimeException::class, 'The chat notification channel must use a processable Monolog handler.');

test('the tap attaches the processor to a processable handler', function () {
    $handler = new TestHandler;
    $logger = new Logger(new Monolog('chat', [$handler]));

    (new StripChatNotificationDetails)($logger);

    $logger->critical('Message about synthetic.person@example.test', ['payload' => ['id' => 1]]);

    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->message)->not->toContain('synthetic.person@example.test')
        ->and($handler->getRecords()[0]->context)->toBe([]);
});
