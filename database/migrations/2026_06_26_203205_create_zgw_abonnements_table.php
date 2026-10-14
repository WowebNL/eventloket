<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One Open Notificaties abonnement per ZGW connection. Tracks the abonnement
     * URL, the Passport access token that the remote Notificaties API presents on
     * our shared webhook (the auth value), and its expiry so the auto-renew job can
     * rotate the token before it lapses.
     *
     * municipality_id is derived from the connection name ("gemeente_{id}"); the
     * shared "main" connection stays null.
     */
    public function up(): void
    {
        Schema::create('zgw_abonnements', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->unique();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notificaties_base_url');
            $table->string('callback_url');
            $table->string('abonnement_url')->nullable();
            $table->string('token_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zgw_abonnements');
    }
};
