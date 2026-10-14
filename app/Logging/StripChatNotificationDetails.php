<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as Monolog;
use RuntimeException;

/**
 * Log channel tap for chat notification channels (the "slack" channel).
 *
 * The processor is pushed onto the channel's handlers rather than onto the logger. Laravel's
 * stack driver copies logger-level processors of every channel in the stack into the shared
 * stack logger, so a logger-level processor would also strip the records written to files and
 * other channels. A handler-level processor only affects the chat handler.
 *
 * The tap fails closed: when the processor cannot be attached, it throws instead of letting
 * the channel send unfiltered records. Laravel then falls back to its emergency logger.
 */
final class StripChatNotificationDetails
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            throw new RuntimeException('The chat notification channel must use a processable Monolog handler.');
        }

        $processor = new ChatSafeRecordProcessor(
            (string) config('app.env'),
            base_path(),
        );

        $attached = false;

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor($processor);
                $attached = true;
            }
        }

        if (! $attached) {
            throw new RuntimeException('The chat notification channel must use a processable Monolog handler.');
        }
    }
}
