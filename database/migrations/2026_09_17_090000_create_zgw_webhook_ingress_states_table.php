<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single row describing the state of the shared Open Notificaties webhook:
     * when a delivery was last accepted, and how long we have been refusing them
     * in a row. Every ZGW connection posts to the same endpoint, so this is one
     * state for the ingress as a whole and not one per connection.
     *
     * It deliberately holds counters and timestamps only. Request metadata (source
     * address, user agent, path) stays in the log, and request bodies and URLs are
     * never stored anywhere because ZGW query filters can carry personal data.
     *
     * This lives in the database rather than the cache because the whole point is
     * to notice an outage that outlives a deploy, and a deploy empties the cache.
     */
    public function up(): void
    {
        Schema::create('zgw_webhook_ingress_states', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_accepted_at')->nullable();
            $table->unsignedInteger('consecutive_rejections')->default(0);
            $table->timestamp('rejection_streak_started_at')->nullable();
            $table->unsignedSmallInteger('last_rejection_status')->nullable();
            $table->timestamp('last_rejection_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zgw_webhook_ingress_states');
    }
};
