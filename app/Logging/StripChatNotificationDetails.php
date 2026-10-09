<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as Monolog;

/**
 * Log channel tap for chat notification channels (the "slack" channel).
 *
 * The processor is pushed onto the channel's handlers rather than onto the logger. Laravel's
 * stack driver copies logger-level processors of every channel in the stack into the shared
 * stack logger, so a logger-level processor would also strip the records written to files and
 * other channels. A handler-level processor only affects the chat handler.
 */
final class StripChatNotificationDetails
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $processor = new ChatSafeRecordProcessor(
            (string) config('app.env'),
            base_path(),
        );

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor($processor);
            }
        }
    }
}
