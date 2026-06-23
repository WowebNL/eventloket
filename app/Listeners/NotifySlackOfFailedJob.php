<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;

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
                'title' => $exception->getMessage(),
                'text' => substr($exception->getTraceAsString(), 0, 2900),
                'ts' => now()->timestamp,
            ]],
        ]);
    }
}
