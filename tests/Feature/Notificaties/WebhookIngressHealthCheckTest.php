<?php

declare(strict_types=1);

use App\Models\ZgwAbonnement;
use App\Models\ZgwWebhookIngressState;
use App\Services\Notificaties\WebhookIngressHealthCheck;
use App\Services\Notificaties\WebhookIngressStatus;

/**
 * Write an ingress state directly, so each case states exactly the situation it
 * describes instead of reaching it through a sequence of requests.
 */
function ingressState(array $attributes): ZgwWebhookIngressState
{
    $state = new ZgwWebhookIngressState;

    $state->forceFill(array_merge([
        'id' => ZgwWebhookIngressState::SINGLETON_ID,
        'last_accepted_at' => null,
        'consecutive_rejections' => 0,
        'rejection_streak_started_at' => null,
        'last_rejection_status' => null,
        'last_rejection_at' => null,
    ], $attributes))->save();

    return $state;
}

function ingressStatus(): WebhookIngressStatus
{
    return app(WebhookIngressHealthCheck::class)->run();
}

it('is healthy when nothing has been recorded yet', function () {
    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('reports a rejection streak that clears both the count and the window', function () {
    ingressState([
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD,
        'rejection_streak_started_at' => now()->subHours(WebhookIngressHealthCheck::REJECTION_WINDOW_HOURS + 1),
    ]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Rejecting);
});

it('stays quiet for a burst of rejections inside the window', function () {
    // The count is met many times over, but it all happened just now: this is
    // what something probing the endpoint looks like, not a standing fault.
    ingressState([
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD * 10,
        'rejection_streak_started_at' => now()->subMinutes(5),
    ]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('stays quiet for too few rejections however long they have been running', function () {
    ingressState([
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD - 1,
        'rejection_streak_started_at' => now()->subDays(30),
    ]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('reports silence once an abonnement is registered and nothing has arrived', function () {
    ZgwAbonnement::factory()->create();
    ingressState(['last_accepted_at' => now()->subHours(WebhookIngressHealthCheck::SILENCE_HOURS + 1)]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Silent);
});

it('stays quiet while the silence is still within the window', function () {
    ZgwAbonnement::factory()->create();
    ingressState(['last_accepted_at' => now()->subHours(WebhookIngressHealthCheck::SILENCE_HOURS - 1)]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('does not call silence a fault when no abonnement is registered', function () {
    ingressState(['last_accepted_at' => now()->subYear()]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('does not call a freshly deployed environment silent', function () {
    // Deliveries are expected, but none has ever been accepted, so there is no
    // working state to have lost and nothing to conclude from the quiet.
    ZgwAbonnement::factory()->create();
    ingressState(['last_accepted_at' => null]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy);
});

it('reports a rejection streak ahead of silence', function () {
    ZgwAbonnement::factory()->create();
    ingressState([
        'last_accepted_at' => now()->subYear(),
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD,
        'rejection_streak_started_at' => now()->subDays(8),
    ]);

    expect(ingressStatus())->toBe(WebhookIngressStatus::Rejecting);
});

it('clears a rejection streak once a delivery is accepted again', function () {
    ingressState([
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD,
        'rejection_streak_started_at' => now()->subDays(2),
    ]);

    ZgwWebhookIngressState::recordAccepted();

    expect(ingressStatus())->toBe(WebhookIngressStatus::Healthy)
        ->and(ZgwWebhookIngressState::current()->consecutive_rejections)->toBe(0)
        ->and(ZgwWebhookIngressState::current()->rejection_streak_started_at)->toBeNull();
});

it('keeps the streak start on the first rejection while counting the rest', function () {
    ZgwWebhookIngressState::recordRejection(403);
    $started = ZgwWebhookIngressState::current()->rejection_streak_started_at;

    $this->travel(2)->hours();

    ZgwWebhookIngressState::recordRejection(403);
    ZgwWebhookIngressState::recordRejection(401);

    $state = ZgwWebhookIngressState::current();

    expect($state->consecutive_rejections)->toBe(3)
        ->and($state->rejection_streak_started_at->toIso8601String())->toBe($started->toIso8601String())
        ->and($state->last_rejection_status)->toBe(401);
});
