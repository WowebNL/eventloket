<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Reduces a log record to a minimal summary before it is sent to a chat notification channel.
 *
 * Chat notifications only need to tell someone that something went wrong and where to look.
 * The full record (exception message, stack trace, context and extra data such as request
 * input or user identifiers) stays available in the regular log channels and the error
 * tracker; it is not forwarded to the chat service.
 *
 * What is kept: the level (added by the Slack handler itself), the exception class, the file
 * and line the exception was thrown from, the environment and the time of the record.
 *
 * The exception message is dropped as well, because messages regularly contain user input
 * such as e-mail addresses or names.
 */
final class ChatSafeRecordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly string $environment,
        private readonly string $basePath,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;

        $extra = [];

        if ($exception instanceof Throwable) {
            $extra['exception'] = $exception::class;
            $extra['source'] = self::sourceOf($exception, $this->basePath);

            $message = sprintf('%s in %s', $extra['exception'], $extra['source']);
        } else {
            $message = 'Log message withheld from chat; see the application log for details.';
        }

        $extra['environment'] = $this->environment;
        $extra['time'] = $record->datetime->format(DATE_ATOM);

        return $record->with(message: $message, context: [], extra: $extra);
    }

    /**
     * The file (relative to the application root) and line an exception was thrown from.
     */
    public static function sourceOf(Throwable $exception, string $basePath): string
    {
        $path = $exception->getFile();
        $prefix = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $file = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : basename($path);

        return $file.':'.$exception->getLine();
    }
}
