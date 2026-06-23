<?php

use App\Listeners\NotifySlackOfFailedJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->webhookUrl = 'https://hooks.slack.com/services/test/webhook';
});

function makeFailedJobEvent(string $jobName = 'App\\Jobs\\TestJob', string $queue = 'default', string $message = 'Something went wrong'): JobFailed
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn($jobName);
    $job->shouldReceive('getQueue')->andReturn($queue);

    return new JobFailed('redis', $job, new RuntimeException($message));
}

test('sends slack notification with correct payload when webhook url is configured', function () {
    config(['services.slack.horizon_webhook_url' => $this->webhookUrl]);
    Http::fake([$this->webhookUrl => Http::response('ok', 200)]);

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent());

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === $this->webhookUrl
            && str_contains($body['text'], config('app.name'))
            && str_contains($body['text'], 'App\\Jobs\\TestJob')
            && str_contains($body['text'], 'default')
            && $body['attachments'][0]['color'] === 'danger'
            && $body['attachments'][0]['title'] === 'Something went wrong';
    });
});

test('slack message text starts with app name', function () {
    config(['services.slack.horizon_webhook_url' => $this->webhookUrl]);
    config(['app.name' => 'Eventloket']);
    Http::fake([$this->webhookUrl => Http::response('ok', 200)]);

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent());

    Http::assertSent(fn ($request) => str_starts_with($request->data()['text'], '*[Eventloket]'));
});

test('slack message includes queue name', function () {
    config(['services.slack.horizon_webhook_url' => $this->webhookUrl]);
    Http::fake([$this->webhookUrl => Http::response('ok', 200)]);

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent(queue: 'high'));

    Http::assertSent(fn ($request) => str_contains($request->data()['text'], '`high`'));
});

test('slack message includes stack trace in attachment', function () {
    config(['services.slack.horizon_webhook_url' => $this->webhookUrl]);
    Http::fake([$this->webhookUrl => Http::response('ok', 200)]);

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent(message: 'Unique error XYZ'));

    Http::assertSent(function ($request) {
        $attachment = $request->data()['attachments'][0];

        return $attachment['title'] === 'Unique error XYZ'
            && isset($attachment['text'])
            && isset($attachment['ts']);
    });
});

test('stack trace is truncated to 2900 characters', function () {
    config(['services.slack.horizon_webhook_url' => $this->webhookUrl]);
    Http::fake([$this->webhookUrl => Http::response('ok', 200)]);

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent());

    Http::assertSent(fn ($request) => strlen($request->data()['attachments'][0]['text']) <= 2900);
});

test('does not send slack notification when webhook url is not configured', function () {
    config(['services.slack.horizon_webhook_url' => null]);
    Http::fake();

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent());

    Http::assertNothingSent();
});

test('does not send slack notification when webhook url is empty string', function () {
    config(['services.slack.horizon_webhook_url' => '']);
    Http::fake();

    (new NotifySlackOfFailedJob)->handle(makeFailedJobEvent());

    Http::assertNothingSent();
});
