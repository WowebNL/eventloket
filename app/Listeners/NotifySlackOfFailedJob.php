<?php

namespace App\Listeners;

use App\Logging\ChatSafeRecordProcessor;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;

/**
 * Posts a short notice about a failed queued job to a chat webhook.
 *
 * Only the job class, queue, exception class, source location, environment and time are
 * sent. The exception message, stack trace and job payload are deliberately left out: they
 * can carry user data and remain available in the regular logs and the error tracker.
 */
class NotifySlackOfFailedJob
{
    public function handle(JobFailed $event): void
    {
        $webhookUrl = config('services.slack.horizon_webhook_url');

        if (! $webhookUrl) {
            return;
        }

        $jobName = $event->job->resolveName();
        $queue = $event->job->getQueue();
        $exception = $event->exception;

        $appName = config('app.name');

        Http::post($webhookUrl, [
            'text' => "*[{$appName}] Failed job*: `{$jobName}` on queue `{$queue}`",
            'attachments' => [[
                'color' => 'danger',
                'title' => $exception::class,
                'fields' => [
                    [
                        'title' => 'Source',
                        'value' => ChatSafeRecordProcessor::sourceOf($exception, base_path()),
                        'short' => false,
                    ],
                    [
                        'title' => 'Environment',
                        'value' => (string) config('app.env'),
                        'short' => true,
                    ],
                ],
                'ts' => now()->timestamp,
            ]],
        ]);
    }
}
