<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwAbonnement;
use App\Models\ZgwWebhookIngressState;

/**
 * Judges the shared Open Notificaties webhook by how it is actually behaving,
 * as a complement to {@see AbonnementHealthCheck}, which judges whether the
 * abonnementen behind it are correctly registered.
 *
 * The two answer different questions and neither subsumes the other. A
 * registration can look perfect while every delivery bounces, and a registration
 * that was never made produces no traffic at all to judge. This check covers the
 * first case and the tail of the second.
 */
class WebhookIngressHealthCheck
{
    /**
     * A rejection streak has to clear both of these before it counts as a fault.
     *
     * Each bound on its own is trivially reached and would make the signal fire
     * on noise: a handful of refusals inside a minute is something probing a
     * public endpoint, and two refusals six hours apart are two unrelated
     * incidents. Together they say something narrower and more useful — we have
     * refused repeatedly, across a meaningful stretch of time, and nothing got
     * through in between. A signal that fires on a single refusal gets ignored,
     * and then it is worse than no signal at all.
     */
    public const REJECTION_THRESHOLD = 3;

    public const REJECTION_WINDOW_HOURS = 6;

    /**
     * How long the ingress may stay quiet before silence itself is the fault.
     *
     * Notifications follow status changes, documents, besluiten and zaaktypen,
     * so the traffic is driven by office hours and a long weekend can legitimately
     * be quiet for days. Three days is the shortest window that cannot be tripped
     * by that, which is the point: this bound has to be conservative because the
     * check cannot distinguish "nothing happened" from "nothing arrived".
     */
    public const SILENCE_HOURS = 72;

    public function run(): WebhookIngressStatus
    {
        $state = ZgwWebhookIngressState::current();

        if ($state === null) {
            return WebhookIngressStatus::Healthy;
        }

        if ($this->isRejecting($state)) {
            return WebhookIngressStatus::Rejecting;
        }

        if ($this->hasGoneSilent($state)) {
            return WebhookIngressStatus::Silent;
        }

        return WebhookIngressStatus::Healthy;
    }

    private function isRejecting(ZgwWebhookIngressState $state): bool
    {
        if ($state->consecutive_rejections < self::REJECTION_THRESHOLD) {
            return false;
        }

        return $state->rejection_streak_started_at !== null
            && $state->rejection_streak_started_at->lte(now()->subHours(self::REJECTION_WINDOW_HOURS));
    }

    /**
     * Silence only means something once two things hold: an abonnement is
     * registered (so deliveries are expected at all), and a delivery has been
     * accepted at least once before (so there is a working state to have lost).
     * Without the second condition a freshly deployed environment would report a
     * fault for its first three days.
     */
    private function hasGoneSilent(ZgwWebhookIngressState $state): bool
    {
        if ($state->last_accepted_at === null) {
            return false;
        }

        if (! ZgwAbonnement::query()->exists()) {
            return false;
        }

        return $state->last_accepted_at->lt(now()->subHours(self::SILENCE_HOURS));
    }
}
