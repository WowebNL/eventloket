<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short opaque webhook tokens ("elw.<lookup>.<secret>") for connections whose
     * Notificaties API cannot store a Passport access token in an abonnement.
     *
     * Only a SHA-256 hash of the secret is stored. The lookup is not a secret; it
     * finds the row without the secret ever reaching a query. Revoking clears the
     * hash but keeps the lookup, so a refused delivery of a revoked token can
     * still be recognised as our own credential by the ingress bookkeeping.
     */
    public function up(): void
    {
        Schema::create('zgw_webhook_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->index();
            $table->char('lookup', 32)->unique();
            $table->char('secret_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zgw_webhook_tokens');
    }
};
